<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Order;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Get all reviews
     */
    public function index(Request $request)
    {
        $query = Review::with(['reviewer', 'target', 'order'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->has('target_id')) {
            $query->where('target_id', $request->target_id);
        }

        if ($request->has('reviewer_id')) {
            $query->where('reviewer_id', $request->reviewer_id);
        }

        if ($request->has('helpful')) {
            $query->where('is_helpful', $request->boolean('helpful'));
        }

        $reviews = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'reviews' => $reviews
        ]);
    }

    /**
     * Get single review
     */
    public function show(Review $review)
    {
        $review->load(['reviewer', 'target', 'order']);

        return response()->json([
            'success' => true,
            'review' => [
                'id' => $review->id,
                'type' => $review->type,
                'rating' => $review->rating,
                'formatted_rating' => $review->formatted_rating,
                'rating_badge' => $review->rating_badge,
                'title' => $review->title,
                'comment' => $review->comment,
                'is_helpful' => $review->is_helpful,
                'is_reported' => $review->is_reported,
                'status' => $review->status,
                'status_badge' => $review->status_badge,
                'reviewer' => [
                    'id' => $review->reviewer->id,
                    'name' => $review->reviewer->name,
                    'avatar' => $review->reviewer->avatar,
                    'is_verified' => $review->reviewer->is_verified
                ],
                'target' => [
                    'id' => $review->target->id,
                    'name' => $review->target->name ?? $review->target->title,
                    'type' => $review->type
                ],
                'order' => $review->order ? [
                    'id' => $review->order->id,
                    'item_title' => $review->order->item_title,
                    'total_amount' => $review->order->total_amount
                ] : null,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at
            ]
        ]);
    }

    /**
     * Create new review
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_id' => 'required|integer',
            'type' => 'required|string|in:item,seller',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'order_id' => 'sometimes|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $user = $request->user();

        // Check if target exists
        if ($request->type === 'item') {
            $target = Item::find($request->target_id);
        } else {
            $target = User::find($request->target_id);
        }

        if (!$target) {
            return response()->json([
                'success' => false,
                'error' => 'Target not found'
            ], 404);
        }

        // Check if user has already reviewed this target
        $existingReview = Review::where('reviewer_id', $user->id)
            ->where('target_id', $request->target_id)
            ->where('type', $request->type)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'error' => 'You have already reviewed this ' . $request->type
            ], 400);
        }

        // Check if user can review (must have completed an order)
        if ($request->has('order_id')) {
            $order = Order::find($request->order_id);
            if (!$order || $order->buyer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid order for review'
                ], 400);
            }

            if ($order->status !== 'delivered') {
                return response()->json([
                    'success' => false,
                    'error' => 'Order must be delivered before reviewing'
                ], 400);
            }
        }

        $review = Review::create([
            'reviewer_id' => $user->id,
            'target_id' => $request->target_id,
            'order_id' => $request->order_id,
            'type' => $request->type,
            'rating' => $request->rating,
            'title' => $request->title,
            'comment' => $request->comment,
            'status' => 'pending'
        ]);

        $review->load(['reviewer', 'target', 'order']);

        return response()->json([
            'success' => true,
            'review' => $review,
            'message' => 'Review submitted successfully'
        ], 201);
    }

    /**
     * Update review
     */
    public function update(Request $request, Review $review)
    {
        // Check if user owns the review
        if ($review->reviewer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to update this review'
            ], 403);
        }

        // Check if review can be updated
        if ($review->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'Review cannot be updated after approval'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'title' => 'sometimes|string|max:255',
            'comment' => 'sometimes|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $review->update($request->only(['rating', 'title', 'comment']));
        $review->load(['reviewer', 'target', 'order']);

        return response()->json([
            'success' => true,
            'review' => $review,
            'message' => 'Review updated successfully'
        ]);
    }

    /**
     * Delete review
     */
    public function destroy(Review $review)
    {
        // Check if user owns the review
        if ($review->reviewer_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to delete this review'
            ], 403);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    }

    /**
     * Mark review as helpful
     */
    public function markAsHelpful(Request $request, Review $review)
    {
        $validator = Validator::make($request->all(), [
            'helpful' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $review->update(['is_helpful' => $request->boolean('helpful')]);

        return response()->json([
            'success' => true,
            'review' => $review,
            'message' => $request->boolean('helpful') 
                ? 'Review marked as helpful' 
                : 'Review marked as not helpful'
        ]);
    }

    /**
     * Report review
     */
    public function report(Request $request, Review $review)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $review->update([
            'is_reported' => true,
            'reported_reason' => $request->reason,
            'status' => 'reported'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review reported successfully'
        ]);
    }

    /**
     * Get reviews for specific target
     */
    public function getTargetReviews(Request $request, $targetId, $type)
    {
        $query = Review::where('target_id', $targetId)
            ->where('type', $type)
            ->where('status', 'approved')
            ->with(['reviewer'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->has('helpful')) {
            $query->where('is_helpful', $request->boolean('helpful'));
        }

        $reviews = $query->paginate($request->get('per_page', 15));

        // Calculate statistics
        $stats = [
            'total_reviews' => $reviews->total(),
            'average_rating' => Review::where('target_id', $targetId)
                ->where('type', $type)
                ->where('status', 'approved')
                ->avg('rating'),
            'rating_distribution' => [
                '5' => Review::where('target_id', $targetId)->where('type', $type)->where('rating', 5)->count(),
                '4' => Review::where('target_id', $targetId)->where('type', $type)->where('rating', 4)->count(),
                '3' => Review::where('target_id', $targetId)->where('type', $type)->where('rating', 3)->count(),
                '2' => Review::where('target_id', $targetId)->where('type', $type)->where('rating', 2)->count(),
                '1' => Review::where('target_id', $targetId)->where('type', $type)->where('rating', 1)->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'stats' => $stats
        ]);
    }
}
