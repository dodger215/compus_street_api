<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // User routes
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/users/{user}/listings', [UserController::class, 'listings']);
    Route::get('/users/{user}/reviews', [UserController::class, 'reviews']);
    Route::get('/users/{user}/stats', [UserController::class, 'stats']);

    // Item routes
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{item}', [ItemController::class, 'show']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{item}', [ItemController::class, 'update']);
    Route::delete('/items/{item}', [ItemController::class, 'destroy']);
    Route::post('/items/{item}/view', [ItemController::class, 'incrementView']);
    Route::post('/items/{item}/wishlist', [ItemController::class, 'toggleWishlist']);
    Route::get('/items/{item}/reviews', [ItemController::class, 'reviews']);

    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/{order}/ship', [OrderController::class, 'ship']);
    Route::post('/orders/{order}/deliver', [OrderController::class, 'deliver']);

    // Message routes
    Route::get('/conversations', [MessageController::class, 'conversations']);
    Route::get('/conversations/{conversation}', [MessageController::class, 'show']);
    Route::post('/conversations', [MessageController::class, 'create']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'sendMessage']);
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'messages']);
    Route::put('/conversations/{conversation}/read', [MessageController::class, 'markAsRead']);

    // Review routes
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/helpful', [ReviewController::class, 'markAsHelpful']);
    Route::post('/reviews/{review}/report', [ReviewController::class, 'report']);

    // Payment routes
    Route::post('/payments/initialize', [PaymentController::class, 'initialize']);
    Route::post('/payments/verify', [PaymentController::class, 'verify']);
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::get('/payments', [PaymentController::class, 'index']);

    // Search routes
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
    Route::get('/search/categories', [SearchController::class, 'categories']);
    Route::get('/search/filters', [SearchController::class, 'filters']);

    // Analytics routes
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/user', [AnalyticsController::class, 'userAnalytics']);
    Route::get('/analytics/items', [AnalyticsController::class, 'itemAnalytics']);
    Route::get('/analytics/orders', [AnalyticsController::class, 'orderAnalytics']);
    Route::post('/analytics/track', [AnalyticsController::class, 'track']);

    // Admin routes (protected by admin middleware)
    Route::middleware('admin')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::get('/admin/items', [AdminController::class, 'items']);
        Route::get('/admin/orders', [AdminController::class, 'orders']);
        Route::get('/admin/payments', [AdminController::class, 'payments']);
        Route::get('/admin/analytics', [AdminController::class, 'analytics']);
        Route::post('/admin/items/{item}/approve', [AdminController::class, 'approveItem']);
        Route::post('/admin/items/{item}/reject', [AdminController::class, 'rejectItem']);
        Route::post('/admin/users/{user}/verify', [AdminController::class, 'verifyUser']);
        Route::post('/admin/users/{user}/suspend', [AdminController::class, 'suspendUser']);
        Route::post('/admin/reviews/{review}/moderate', [AdminController::class, 'moderateReview']);
    });
});

// Webhook routes (no authentication required)
Route::post('/webhooks/paystack', [PaymentController::class, 'paystackWebhook']);
Route::post('/webhooks/email', [MessageController::class, 'emailWebhook']);

// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});
