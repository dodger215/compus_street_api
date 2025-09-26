<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\User;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    /**
     * Search items and users
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
            'type' => 'sometimes|string|in:items,users,all',
            'category' => 'sometimes|string',
            'location' => 'sometimes|string',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'condition' => 'sometimes|string',
            'sort_by' => 'sometimes|string|in:relevance,price,date,rating,views',
            'sort_order' => 'sometimes|string|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $query = $request->q;
        $type = $request->get('type', 'all');
        $results = [];

        // Track search event
        AnalyticsEvent::trackSearch(
            $request->user()->id,
            $query,
            0 // Will be updated after search
        );

        // Search items
        if ($type === 'all' || $type === 'items') {
            $itemsQuery = Item::with(['seller', 'reviews'])
                ->where('is_available', true)
                ->where('status', 'available')
                ->search($query);

            // Apply filters
            if ($request->has('category')) {
                $itemsQuery->where('category', $request->category);
            }

            if ($request->has('location')) {
                $itemsQuery->where('location', 'like', '%' . $request->location . '%');
            }

            if ($request->has('min_price')) {
                $itemsQuery->where('price', '>=', $request->min_price);
            }

            if ($request->has('max_price')) {
                $itemsQuery->where('price', '<=', $request->max_price);
            }

            if ($request->has('condition')) {
                $itemsQuery->where('condition', $request->condition);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'relevance');
            $sortOrder = $request->get('sort_order', 'desc');

            switch ($sortBy) {
                case 'price':
                    $itemsQuery->orderBy('price', $sortOrder);
                    break;
                case 'date':
                    $itemsQuery->orderBy('created_at', $sortOrder);
                    break;
                case 'rating':
                    $itemsQuery->orderBy('average_rating', $sortOrder);
                    break;
                case 'views':
                    $itemsQuery->orderBy('views_count', $sortOrder);
                    break;
                default: // relevance
                    $itemsQuery->orderBy('created_at', 'desc');
                    break;
            }

            $items = $itemsQuery->paginate($request->get('per_page', 15));
            $results['items'] = $items;
        }

        // Search users
        if ($type === 'all' || $type === 'users') {
            $usersQuery = User::where(function($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%')
                  ->orWhere('email', 'like', '%' . $query . '%')
                  ->orWhere('bio', 'like', '%' . $query . '%');
            });

            $users = $usersQuery->paginate($request->get('per_page', 15));
            $results['users'] = $users;
        }

        // Get search suggestions
        $suggestions = $this->getSearchSuggestions($query);

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $results,
            'suggestions' => $suggestions,
            'filters' => [
                'categories' => $this->getCategories(),
                'conditions' => $this->getConditions(),
                'locations' => $this->getLocations()
            ]
        ]);
    }

    /**
     * Get search suggestions
     */
    public function suggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $query = $request->q;
        $suggestions = $this->getSearchSuggestions($query);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Get search categories
     */
    public function categories()
    {
        $categories = $this->getCategories();
        $categoryStats = Item::selectRaw('category, COUNT(*) as count')
            ->where('is_available', true)
            ->where('status', 'available')
            ->groupBy('category')
            ->pluck('count', 'category');

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'stats' => $categoryStats
        ]);
    }

    /**
     * Get search filters
     */
    public function filters()
    {
        return response()->json([
            'success' => true,
            'filters' => [
                'categories' => $this->getCategories(),
                'conditions' => $this->getConditions(),
                'locations' => $this->getLocations(),
                'price_ranges' => $this->getPriceRanges()
            ]
        ]);
    }

    /**
     * Get search suggestions
     */
    private function getSearchSuggestions($query)
    {
        $suggestions = [];

        // Get popular search terms
        $popularTerms = AnalyticsEvent::where('event_type', 'search')
            ->where('event_name', 'search_performed')
            ->where('properties->query', 'like', '%' . $query . '%')
            ->selectRaw('JSON_EXTRACT(properties, "$.query") as query, COUNT(*) as count')
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->pluck('query');

        $suggestions['popular_terms'] = $popularTerms;

        // Get item title suggestions
        $itemTitles = Item::where('title', 'like', '%' . $query . '%')
            ->where('is_available', true)
            ->where('status', 'available')
            ->select('title')
            ->distinct()
            ->limit(5)
            ->pluck('title');

        $suggestions['item_titles'] = $itemTitles;

        // Get category suggestions
        $categories = Item::where('category', 'like', '%' . $query . '%')
            ->where('is_available', true)
            ->where('status', 'available')
            ->select('category')
            ->distinct()
            ->limit(3)
            ->pluck('category');

        $suggestions['categories'] = $categories;

        return $suggestions;
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
            ->where('is_available', true)
            ->where('status', 'available')
            ->pluck('location')
            ->sort()
            ->values();
    }

    /**
     * Get price ranges
     */
    private function getPriceRanges()
    {
        $priceStats = Item::where('is_available', true)
            ->where('status', 'available')
            ->selectRaw('
                MIN(price) as min_price,
                MAX(price) as max_price,
                AVG(price) as avg_price
            ')
            ->first();

        return [
            'min' => $priceStats->min_price ?? 0,
            'max' => $priceStats->max_price ?? 1000,
            'average' => $priceStats->avg_price ?? 100,
            'ranges' => [
                '0-50' => 'Under GH₵50',
                '50-100' => 'GH₵50 - GH₵100',
                '100-200' => 'GH₵100 - GH₵200',
                '200-500' => 'GH₵200 - GH₵500',
                '500+' => 'Above GH₵500'
            ]
        ];
    }
}
