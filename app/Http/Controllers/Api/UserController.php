<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Item;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get user profile
     */
    public function show(User $user)
    {
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
                'avatar' => $user->avatar,
                'is_verified' => $user->is_verified,
                'college_domain' => $user->college_domain,
                'average_rating' => $user->average_rating,
                'reviews_count' => $user->reviews_count,
                'listings_count' => $user->listings_count,
                'orders_count' => $user->orders_count,
                'sales_count' => $user->sales_count,
                'created_at' => $user->created_at,
                'last_login' => $user->last_login
            ]
        ]);
    }

    /**
     * Get user's listings
     */
    public function listings(User $user, Request $request)
    {
        $query = $user->listings()->with(['seller', 'reviews']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_premium')) {
            $query->where('is_premium', $request->boolean('is_premium'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $listings = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'listings' => $listings,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'listings_count' => $user->listings_count
            ]
        ]);
    }

    /**
     * Get user's reviews
     */
    public function reviews(User $user, Request $request)
    {
        $query = $user->receivedReviews()
            ->with(['reviewer', 'order'])
            ->where('status', 'approved');

        // Apply filters
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $reviews = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'average_rating' => $user->average_rating,
                'reviews_count' => $user->reviews_count
            ]
        ]);
    }

    /**
     * Get user's stats
     */
    public function stats(User $user)
    {
        $stats = [
            'listings' => [
                'total' => $user->listings_count,
                'available' => $user->listings()->where('status', 'available')->count(),
                'sold' => $user->listings()->where('status', 'sold')->count(),
                'premium' => $user->listings()->where('is_premium', true)->count()
            ],
            'orders' => [
                'total' => $user->orders_count,
                'pending' => $user->orders()->where('status', 'pending')->count(),
                'completed' => $user->orders()->where('status', 'delivered')->count(),
                'cancelled' => $user->orders()->where('status', 'cancelled')->count()
            ],
            'sales' => [
                'total' => $user->sales_count,
                'pending' => $user->sales()->where('status', 'pending')->count(),
                'completed' => $user->sales()->where('status', 'delivered')->count(),
                'cancelled' => $user->sales()->where('status', 'cancelled')->count()
            ],
            'reviews' => [
                'total' => $user->reviews_count,
                'average_rating' => $user->average_rating,
                'by_rating' => [
                    '5' => $user->receivedReviews()->where('rating', 5)->count(),
                    '4' => $user->receivedReviews()->where('rating', 4)->count(),
                    '3' => $user->receivedReviews()->where('rating', 3)->count(),
                    '2' => $user->receivedReviews()->where('rating', 2)->count(),
                    '1' => $user->receivedReviews()->where('rating', 1)->count()
                ]
            ],
            'wishlist' => [
                'total' => $user->wishlist_count
            ],
            'activity' => [
                'last_login' => $user->last_login,
                'member_since' => $user->created_at,
                'total_views' => $user->listings()->sum('views_count')
            ]
        ];

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_verified' => $user->is_verified
            ],
            'stats' => $stats
        ]);
    }

    /**
     * Get user's orders
     */
    public function orders(User $user, Request $request)
    {
        $query = $user->orders()
            ->with(['item', 'seller', 'buyer'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'orders_count' => $user->orders_count
            ]
        ]);
    }

    /**
     * Get user's sales
     */
    public function sales(User $user, Request $request)
    {
        $query = $user->sales()
            ->with(['item', 'buyer'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sales = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'sales' => $sales,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'sales_count' => $user->sales_count
            ]
        ]);
    }

    /**
     * Get user's wishlist
     */
    public function wishlist(User $user, Request $request)
    {
        $query = $user->wishlist()
            ->with(['seller', 'reviews'])
            ->orderBy('wishlists.created_at', 'desc');

        // Apply filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $wishlist = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'wishlist' => $wishlist,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'wishlist_count' => $user->wishlist_count
            ]
        ]);
    }

    /**
     * Get user's notifications
     */
    public function notifications(User $user, Request $request)
    {
        $query = $user->notifications()
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('read')) {
            if ($request->boolean('read')) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        $notifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'unread_notifications_count' => $user->unread_notifications_count
            ]
        ]);
    }

    /**
     * Mark notifications as read
     */
    public function markNotificationsAsRead(User $user, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'sometimes|array',
            'notification_ids.*' => 'exists:notifications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $query = $user->notifications()->whereNull('read_at');

        if ($request->has('notification_ids')) {
            $query->whereIn('id', $request->notification_ids);
        }

        $updated = $query->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$updated} notifications as read"
        ]);
    }
}
