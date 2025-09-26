<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\User;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    /**
     * Get all items
     */
    public function index(Request $request)
    {
        $query = Item::with(['seller', 'reviews'])
            ->where('is_available', true)
            ->where('status', 'available');

        // Apply filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('subcategory')) {
            $query->where('subcategory', $request->subcategory);
        }

        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('is_premium')) {
            $query->where('is_premium', $request->boolean('is_premium'));
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortBy === 'price') {
            $query->orderBy('price', $sortOrder);
        } elseif ($sortBy === 'views') {
            $query->orderBy('views_count', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $items = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'items' => $items,
            'filters' => [
                'categories' => $this->getCategories(),
                'conditions' => $this->getConditions(),
                'locations' => $this->getLocations()
            ]
        ]);
    }

    /**
     * Get single item
     */
    public function show(Item $item)
    {
        // Increment view count
        $item->incrementViews();

        $item->load(['seller', 'reviews.reviewer']);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'category' => $item->category,
                'subcategory' => $item->subcategory,
                'price' => $item->price,
                'formatted_price' => $item->formatted_price,
                'condition' => $item->condition,
                'formatted_condition' => $item->formatted_condition,
                'location' => $item->location,
                'images' => $item->images,
                'main_image' => $item->main_image,
                'is_premium' => $item->is_premium,
                'is_available' => $item->is_available,
                'views_count' => $item->views_count,
                'status' => $item->status,
                'status_badge' => $item->status_badge,
                'condition_badge' => $item->condition_badge,
                'average_rating' => $item->average_rating,
                'reviews_count' => $item->reviews_count,
                'wishlist_count' => $item->wishlist_count,
                'seller' => [
                    'id' => $item->seller->id,
                    'name' => $item->seller->name,
                    'avatar' => $item->seller->avatar,
                    'is_verified' => $item->seller->is_verified,
                    'average_rating' => $item->seller->average_rating,
                    'reviews_count' => $item->seller->reviews_count,
                    'listings_count' => $item->seller->listings_count
                ],
                'reviews' => $item->reviews->map(function($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'title' => $review->title,
                        'comment' => $review->comment,
                        'is_helpful' => $review->is_helpful,
                        'reviewer' => [
                            'id' => $review->reviewer->id,
                            'name' => $review->reviewer->name,
                            'avatar' => $review->reviewer->avatar
                        ],
                        'created_at' => $review->created_at
                    ];
                }),
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ]
        ]);
    }

    /**
     * Create new item
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category' => 'required|string|in:academic_books,electronics,stationery,transportation,furniture,clothing,sports,other',
            'subcategory' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0.01|max:999999.99',
            'condition' => 'required|string|in:new,like_new,good,fair,poor',
            'location' => 'required|string|max:255',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_premium' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Handle image uploads
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('items', 'public');
                $images[] = Storage::url($path);
            }
        }

        $item = Item::create([
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'subcategory' => $request->subcategory,
            'price' => $request->price,
            'condition' => $request->condition,
            'location' => $request->location,
            'images' => $images,
            'is_premium' => $request->boolean('is_premium', false),
            'seller_id' => $request->user()->id,
            'status' => 'available'
        ]);

        $item->load('seller');

        return response()->json([
            'success' => true,
            'item' => $item,
            'message' => 'Item created successfully'
        ], 201);
    }

    /**
     * Update item
     */
    public function update(Request $request, Item $item)
    {
        // Check if user owns the item
        if ($item->seller_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to update this item'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'category' => 'sometimes|string|in:academic_books,electronics,stationery,transportation,furniture,clothing,sports,other',
            'subcategory' => 'nullable|string|max:100',
            'price' => 'sometimes|numeric|min:0.01|max:999999.99',
            'condition' => 'sometimes|string|in:new,like_new,good,fair,poor',
            'location' => 'sometimes|string|max:255',
            'images' => 'sometimes|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_premium' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:available,sold,pending'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $updateData = $request->only(['title', 'description', 'category', 'subcategory', 'price', 'condition', 'location', 'is_premium', 'status']);

        // Handle image uploads if provided
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('items', 'public');
                $images[] = Storage::url($path);
            }
            $updateData['images'] = $images;
        }

        $item->update($updateData);
        $item->load('seller');

        return response()->json([
            'success' => true,
            'item' => $item,
            'message' => 'Item updated successfully'
        ]);
    }

    /**
     * Delete item
     */
    public function destroy(Item $item)
    {
        // Check if user owns the item
        if ($item->seller_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to delete this item'
            ], 403);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    }

    /**
     * Increment item view count
     */
    public function incrementView(Item $item)
    {
        $item->incrementViews();

        return response()->json([
            'success' => true,
            'views_count' => $item->views_count
        ]);
    }

    /**
     * Toggle item in wishlist
     */
    public function toggleWishlist(Request $request, Item $item)
    {
        $user = $request->user();
        
        if ($user->hasInWishlist($item->id)) {
            $user->removeFromWishlist($item->id);
            $action = 'removed';
        } else {
            $user->addToWishlist($item->id);
            $action = 'added';
        }

        return response()->json([
            'success' => true,
            'action' => $action,
            'in_wishlist' => $user->hasInWishlist($item->id),
            'wishlist_count' => $item->wishlist_count
        ]);
    }

    /**
     * Get item reviews
     */
    public function reviews(Item $item, Request $request)
    {
        $query = $item->reviews()
            ->with('reviewer')
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
                'average_rating' => $item->average_rating,
                'reviews_count' => $item->reviews_count
            ]
        ]);
    }

    /**
     * Get categories
     */
    private function getCategories()
    {
        return [
            'academic_books' => 'Academic Books',
            'electronics' => 'Electronics',
            'stationery' => 'Stationery & Supplies',
            'transportation' => 'Transportation',
            'furniture' => 'Furniture',
            'clothing' => 'Clothing',
            'sports' => 'Sports & Fitness',
            'other' => 'Other'
        ];
    }

    /**
     * Get conditions
     */
    private function getConditions()
    {
        return [
            'new' => 'New',
            'like_new' => 'Like New',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor'
        ];
    }

    /**
     * Get locations
     */
    private function getLocations()
    {
        return Item::select('location')
            ->distinct()
            ->whereNotNull('location')
            ->pluck('location')
            ->sort()
            ->values();
    }
}
