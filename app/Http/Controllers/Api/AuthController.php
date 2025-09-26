<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'year' => 'required|integer|min:1|max:6',
            'phone' => 'nullable|string|max:20',
            'college_domain' => 'required|string|in:gctu.edu.gh,knust.edu.gh,ug.edu.gh,ashesi.edu.gh'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Verify college email domain
        $emailDomain = substr(strrchr($request->email, "@"), 1);
        if ($emailDomain !== $request->college_domain) {
            return response()->json([
                'success' => false,
                'error' => 'Email domain does not match selected college'
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'year' => $request->year,
            'phone' => $request->phone,
            'college_domain' => $request->college_domain,
            'is_verified' => false,
            'preferences' => json_encode([
                'notifications' => true,
                'email_updates' => true,
                'sms_updates' => false
            ]),
            'stats' => json_encode([
                'listings_count' => 0,
                'orders_count' => 0,
                'reviews_count' => 0,
                'rating' => 0
            ])
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'year' => $user->year,
                'phone' => $user->phone,
                'is_verified' => $user->is_verified,
                'preferences' => json_decode($user->preferences),
                'stats' => json_decode($user->stats)
            ],
            'token' => $token
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
{
    // ✅ Validate input
    $validator = Validator::make($request->all(), [
        'email'    => 'required|email',
        'password' => 'required|string|min:6',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'error'   => $validator->errors()->first()
        ], 422);
    }

    // ✅ Attempt authentication
    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json([
            'success' => false,
            'error'   => 'Invalid credentials'
        ], 401);
    }

    /** @var \App\Models\User $user */
    $user = Auth::user();

    // ✅ Optional: revoke old tokens (for one active session only)
    // $user->tokens()->delete();

    // ✅ Create new token
    $token = $user->createToken('auth_token')->plainTextToken;

    // ✅ Update last login
    $user->update(['last_login' => now()]);

    // ✅ Response
    return response()->json([
        'success' => true,
        'user'    => [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'year'       => $user->year,
            'phone'      => $user->phone,
            'is_verified'=> (bool) $user->is_verified,
            'preferences'=> json_decode($user->preferences, true),
            'stats'      => json_decode($user->stats, true),
        ],
        'token'   => $token,
        'token_type' => 'Bearer',
    ]);
}

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'year' => $user->year,
                'phone' => $user->phone,
                'is_verified' => $user->is_verified,
                'preferences' => json_decode($user->preferences),
                'stats' => json_decode($user->stats),
                'created_at' => $user->created_at,
                'last_login' => $user->last_login
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'bio' => 'sometimes|nullable|string|max:500',
            'location' => 'sometimes|nullable|string|max:100',
            'preferences' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $updateData = $request->only(['name', 'phone', 'bio', 'location']);
        
        if ($request->has('preferences')) {
            $currentPreferences = json_decode($user->preferences, true);
            $updateData['preferences'] = json_encode(array_merge($currentPreferences, $request->preferences));
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'year' => $user->year,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'location' => $user->location,
                'is_verified' => $user->is_verified,
                'preferences' => json_decode($user->preferences),
                'stats' => json_decode($user->stats)
            ]
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Current password is incorrect'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }
}
