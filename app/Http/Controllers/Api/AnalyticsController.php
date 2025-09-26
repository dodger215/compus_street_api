<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get analytics overview
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $days = $request->get('days', 30);

        // User analytics
        $userStats = [
            'listings' => $user->listings()->count(),
            'orders' => $user->orders()->count(),
            'sales' => $user->sales()->count(),
            'reviews' => $user->receivedReviews()->count(),
            'average_rating' => $user->average_rating,
            'wishlist_count' => $user->wishlist_count
        ];

        // Recent activity
        $recentActivity = AnalyticsEvent::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Event counts by type
        $eventCounts = AnalyticsEvent::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type');

        // Daily activity
        $dailyActivity = AnalyticsEvent::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'overview' => [
                'user_stats' => $userStats,
                'recent_activity' => $recentActivity,
                'event_counts' => $eventCounts,
                'daily_activity' => $dailyActivity,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * Get user analytics
     */
    public function userAnalytics(Request $request)
    {
        $user = $request->user();
        $days = $request->get('days', 30);

        // Profile views
        $profileViews = AnalyticsEvent::where('user_id', $user->id)
            ->where('event_type', 'page_view')
            ->where('properties->page', 'like', '%profile%')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        // Item views
        $itemViews = AnalyticsEvent::where('user_id', $user->id)
            ->where('event_type', 'item_view')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        // Search activity
        $searchActivity = AnalyticsEvent::where('user_id', $user->id)
            ->where('event_type', 'search')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('JSON_EXTRACT(properties, "$.query") as query, COUNT(*) as count')
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Conversion rate
        $totalViews = AnalyticsEvent::where('user_id', $user->id)
            ->where('event_type', 'item_view')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        $purchases = AnalyticsEvent::where('user_id', $user->id)
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        $conversionRate = $totalViews > 0 ? ($purchases / $totalViews) * 100 : 0;

        return response()->json([
            'success' => true,
            'user_analytics' => [
                'profile_views' => $profileViews,
                'item_views' => $itemViews,
                'search_activity' => $searchActivity,
                'conversion_rate' => round($conversionRate, 2),
                'total_views' => $totalViews,
                'purchases' => $purchases,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * Get item analytics
     */
    public function itemAnalytics(Request $request)
    {
        $user = $request->user();
        $days = $request->get('days', 30);

        // Get user's items
        $items = $user->listings()
            ->with(['analyticsEvents'])
            ->get();

        $itemAnalytics = $items->map(function($item) use ($days) {
            $views = $item->analyticsEvents()
                ->where('event_type', 'item_view')
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            $searches = AnalyticsEvent::where('event_type', 'search')
                ->where('properties->query', 'like', '%' . $item->title . '%')
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            return [
                'item_id' => $item->id,
                'title' => $item->title,
                'views' => $views,
                'searches' => $searches,
                'total_views' => $item->views_count,
                'status' => $item->status,
                'is_premium' => $item->is_premium,
                'created_at' => $item->created_at
            ];
        });

        // Top performing items
        $topItems = $itemAnalytics->sortByDesc('views')->take(5);

        // Category performance
        $categoryPerformance = $items->groupBy('category')->map(function($categoryItems) {
            return [
                'category' => $categoryItems->first()->category,
                'count' => $categoryItems->count(),
                'total_views' => $categoryItems->sum('views_count'),
                'average_views' => $categoryItems->avg('views_count')
            ];
        });

        return response()->json([
            'success' => true,
            'item_analytics' => [
                'total_items' => $items->count(),
                'item_analytics' => $itemAnalytics,
                'top_items' => $topItems,
                'category_performance' => $categoryPerformance,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * Get order analytics
     */
    public function orderAnalytics(Request $request)
    {
        $user = $request->user();
        $days = $request->get('days', 30);

        // Order statistics
        $orderStats = [
            'total_orders' => $user->orders()->count(),
            'completed_orders' => $user->orders()->where('status', 'delivered')->count(),
            'pending_orders' => $user->orders()->where('status', 'pending')->count(),
            'cancelled_orders' => $user->orders()->where('status', 'cancelled')->count(),
            'total_spent' => $user->orders()->sum('total_amount'),
            'average_order_value' => $user->orders()->avg('total_amount')
        ];

        // Sales statistics
        $salesStats = [
            'total_sales' => $user->sales()->count(),
            'completed_sales' => $user->sales()->where('status', 'delivered')->count(),
            'pending_sales' => $user->sales()->where('status', 'pending')->count(),
            'cancelled_sales' => $user->sales()->where('status', 'cancelled')->count(),
            'total_earned' => $user->sales()->sum('total_amount'),
            'average_sale_value' => $user->sales()->avg('total_amount')
        ];

        // Recent orders
        $recentOrders = $user->orders()
            ->with(['item', 'seller'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Recent sales
        $recentSales = $user->sales()
            ->with(['item', 'buyer'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Monthly trends
        $monthlyTrends = $user->orders()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count, SUM(total_amount) as total')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'order_analytics' => [
                'order_stats' => $orderStats,
                'sales_stats' => $salesStats,
                'recent_orders' => $recentOrders,
                'recent_sales' => $recentSales,
                'monthly_trends' => $monthlyTrends,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * Track analytics event
     */
    public function track(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|in:page_view,item_view,search,purchase,click,conversion',
            'event_name' => 'required|string|max:100',
            'properties' => 'sometimes|array',
            'item_id' => 'sometimes|exists:items,id',
            'order_id' => 'sometimes|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $event = AnalyticsEvent::create([
            'user_id' => $request->user()->id,
            'item_id' => $request->item_id,
            'order_id' => $request->order_id,
            'event_type' => $request->event_type,
            'event_name' => $request->event_name,
            'properties' => $request->properties ?? [],
            'session_id' => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'event' => $event,
            'message' => 'Event tracked successfully'
        ], 201);
    }

    /**
     * Get analytics dashboard data
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $days = $request->get('days', 30);

        // Quick stats
        $quickStats = [
            'listings' => $user->listings_count,
            'orders' => $user->orders_count,
            'sales' => $user->sales_count,
            'reviews' => $user->reviews_count,
            'rating' => $user->average_rating,
            'wishlist' => $user->wishlist_count
        ];

        // Recent activity
        $recentActivity = AnalyticsEvent::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Performance metrics
        $performanceMetrics = [
            'conversion_rate' => $this->calculateConversionRate($user, $days),
            'average_response_time' => $this->calculateAverageResponseTime($user, $days),
            'customer_satisfaction' => $user->average_rating,
            'repeat_customer_rate' => $this->calculateRepeatCustomerRate($user, $days)
        ];

        return response()->json([
            'success' => true,
            'dashboard' => [
                'quick_stats' => $quickStats,
                'recent_activity' => $recentActivity,
                'performance_metrics' => $performanceMetrics,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * Calculate conversion rate
     */
    private function calculateConversionRate(User $user, $days)
    {
        $views = AnalyticsEvent::where('user_id', $user->id)
            ->where('event_type', 'item_view')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        $purchases = AnalyticsEvent::where('user_id', $user->id)
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        return $views > 0 ? round(($purchases / $views) * 100, 2) : 0;
    }

    /**
     * Calculate average response time
     */
    private function calculateAverageResponseTime(User $user, $days)
    {
        // This would need to be implemented based on message response times
        return 0; // Placeholder
    }

    /**
     * Calculate repeat customer rate
     */
    private function calculateRepeatCustomerRate(User $user, $days)
    {
        $totalCustomers = $user->sales()
            ->where('created_at', '>=', now()->subDays($days))
            ->distinct('buyer_id')
            ->count();

        $repeatCustomers = $user->sales()
            ->where('created_at', '>=', now()->subDays($days))
            ->select('buyer_id')
            ->groupBy('buyer_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        return $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 2) : 0;
    }
}
