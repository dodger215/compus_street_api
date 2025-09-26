<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Item;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get admin dashboard data
     */
    public function dashboard(Request $request)
    {
        $days = $request->get('days', 30);

        // Overall statistics
        $stats = [
            'users' => [
                'total' => User::count(),
                'new_this_period' => User::where('created_at', '>=', now()->subDays($days))->count(),
                'verified' => User::where('is_verified', true)->count(),
                'active' => User::where('last_login', '>=', now()->subDays(7))->count()
            ],
            'items' => [
                'total' => Item::count(),
                'available' => Item::where('status', 'available')->count(),
                'sold' => Item::where('status', 'sold')->count(),
                'pending' => Item::where('status', 'pending')->count(),
                'premium' => Item::where('is_premium', true)->count()
            ],
            'orders' => [
                'total' => Order::count(),
                'pending' => Order::where('status', 'pending')->count(),
                'completed' => Order::where('status', 'delivered')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
                'total_value' => Order::where('status', 'delivered')->sum('total_amount')
            ],
            'payments' => [
                'total' => Payment::count(),
                'successful' => Payment::where('status', 'success')->count(),
                'pending' => Payment::where('status', 'pending')->count(),
                'failed' => Payment::where('status', 'failed')->count(),
                'total_amount' => Payment::where('status', 'success')->sum('amount')
            ],
            'reviews' => [
                'total' => Review::count(),
                'approved' => Review::where('status', 'approved')->count(),
                'pending' => Review::where('status', 'pending')->count(),
                'reported' => Review::where('is_reported', true)->count()
            ]
        ];

        // Recent activity
        $recentActivity = [
            'new_users' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'new_items' => Item::where('created_at', '>=', now()->subDays(7))->count(),
            'new_orders' => Order::where('created_at', '>=', now()->subDays(7))->count(),
            'new_payments' => Payment::where('created_at', '>=', now()->subDays(7))->count()
        ];

        // Top categories
        $topCategories = Item::select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        // Top sellers
        $topSellers = User::withCount('sales')
            ->orderBy('sales_count', 'desc')
            ->limit(5)
            ->get();

        // Revenue trends
        $revenueTrends = Payment::where('status', 'success')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'dashboard' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'top_categories' => $topCategories,
                'top_sellers' => $topSellers,
                'revenue_trends' => $revenueTrends,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * Get all users
     */
    public function users(Request $request)
    {
        $query = User::withCount(['listings', 'orders', 'sales', 'receivedReviews']);

        // Apply filters
        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->boolean('is_verified'));
        }

        if ($request->has('college_domain')) {
            $query->where('college_domain', $request->college_domain);
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Get all items
     */
    public function items(Request $request)
    {
        $query = Item::with(['seller', 'reviews']);

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

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $items = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'items' => $items
        ]);
    }

    /**
     * Get all orders
     */
    public function orders(Request $request)
    {
        $query = Order::with(['item', 'buyer', 'seller']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('buyer_id')) {
            $query->where('buyer_id', $request->buyer_id);
        }

        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $orders = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Get all payments
     */
    public function payments(Request $request)
    {
        $query = Payment::with(['user', 'order']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('plan')) {
            $query->where('plan', $request->plan);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $payments = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'payments' => $payments
        ]);
    }

    /**
     * Get analytics data
     */
    public function analytics(Request $request)
    {
        $days = $request->get('days', 30);

        // User growth
        $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Item growth
        $itemGrowth = Item::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Order growth
        $orderGrowth = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Revenue growth
        $revenueGrowth = Payment::where('status', 'success')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Category distribution
        $categoryDistribution = Item::select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get();

        // College distribution
        $collegeDistribution = User::select('college_domain', DB::raw('COUNT(*) as count'))
            ->groupBy('college_domain')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'analytics' => [
                'user_growth' => $userGrowth,
                'item_growth' => $itemGrowth,
                'order_growth' => $orderGrowth,
                'revenue_growth' => $revenueGrowth,
                'category_distribution' => $categoryDistribution,
                'college_distribution' => $collegeDistribution,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * Approve item
     */
    public function approveItem(Item $item)
    {
        $item->update(['status' => 'available']);

        return response()->json([
            'success' => true,
            'message' => 'Item approved successfully'
        ]);
    }

    /**
     * Reject item
     */
    public function rejectItem(Item $item)
    {
        $item->update(['status' => 'draft']);

        return response()->json([
            'success' => true,
            'message' => 'Item rejected successfully'
        ]);
    }

    /**
     * Verify user
     */
    public function verifyUser(User $user)
    {
        $user->update(['is_verified' => true]);

        return response()->json([
            'success' => true,
            'message' => 'User verified successfully'
        ]);
    }

    /**
     * Suspend user
     */
    public function suspendUser(User $user)
    {
        $user->update(['is_verified' => false]);

        return response()->json([
            'success' => true,
            'message' => 'User suspended successfully'
        ]);
    }

    /**
     * Moderate review
     */
    public function moderateReview(Review $review)
    {
        $validator = Validator::make(request()->all(), [
            'action' => 'required|string|in:approve,reject,delete',
            'reason' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $action = request('action');

        switch ($action) {
            case 'approve':
                $review->update(['status' => 'approved']);
                $message = 'Review approved successfully';
                break;
            case 'reject':
                $review->update(['status' => 'rejected']);
                $message = 'Review rejected successfully';
                break;
            case 'delete':
                $review->delete();
                $message = 'Review deleted successfully';
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }
}
