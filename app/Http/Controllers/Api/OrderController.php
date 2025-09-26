<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Item;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Get user's orders
     */
    public function index(Request $request)
    {
        $user = $request->user();
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
     * Get single order
     */
    public function show(Order $order)
    {
        // Check if user is authorized to view this order
        if ($order->buyer_id !== request()->user()->id && $order->seller_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to view this order'
            ], 403);
        }

        $order->load(['item', 'buyer', 'seller', 'payments']);

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'item_title' => $order->item_title,
                'item_price' => $order->item_price,
                'formatted_item_price' => $order->formatted_item_price,
                'quantity' => $order->quantity,
                'total_amount' => $order->total_amount,
                'formatted_total' => $order->formatted_total,
                'status' => $order->status,
                'formatted_status' => $order->formatted_status,
                'status_badge' => $order->status_badge,
                'payment_status' => $order->payment_status,
                'formatted_payment_status' => $order->formatted_payment_status,
                'payment_status_badge' => $order->payment_status_badge,
                'payment_reference' => $order->payment_reference,
                'shipping_address' => $order->shipping_address,
                'notes' => $order->notes,
                'timeline' => $order->timeline,
                'item' => [
                    'id' => $order->item->id,
                    'title' => $order->item->title,
                    'images' => $order->item->images,
                    'main_image' => $order->item->main_image,
                    'category' => $order->item->category,
                    'condition' => $order->item->condition
                ],
                'buyer' => [
                    'id' => $order->buyer->id,
                    'name' => $order->buyer->name,
                    'email' => $order->buyer->email,
                    'phone' => $order->buyer->phone
                ],
                'seller' => [
                    'id' => $order->seller->id,
                    'name' => $order->seller->name,
                    'email' => $order->seller->email,
                    'phone' => $order->seller->phone
                ],
                'payments' => $order->payments->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'formatted_amount' => $payment->formatted_amount,
                        'status' => $payment->status,
                        'reference' => $payment->reference,
                        'created_at' => $payment->created_at
                    ];
                }),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at
            ]
        ]);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1|max:10',
            'shipping_address' => 'required|string|max:500',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $item = Item::findOrFail($request->item_id);

        // Check if item is available
        if (!$item->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => 'Item is not available for purchase'
            ], 400);
        }

        // Check if user is not trying to buy their own item
        if ($item->seller_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'You cannot purchase your own item'
            ], 400);
        }

        // Calculate total amount
        $totalAmount = $item->price * $request->quantity;

        $order = Order::create([
            'buyer_id' => $request->user()->id,
            'seller_id' => $item->seller_id,
            'item_id' => $item->id,
            'item_title' => $item->title,
            'item_price' => $item->price,
            'quantity' => $request->quantity,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => $request->shipping_address,
            'notes' => $request->notes,
            'timeline' => [
                [
                    'status' => 'pending',
                    'timestamp' => now()->toISOString(),
                    'note' => 'Order created'
                ]
            ]
        ]);

        $order->load(['item', 'buyer', 'seller']);

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => 'Order created successfully'
        ], 201);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,confirmed,shipped,delivered,cancelled,refunded',
            'note' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Check authorization
        $user = $request->user();
        if ($order->buyer_id !== $user->id && $order->seller_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to update this order'
            ], 403);
        }

        // Check if user can update to this status
        if (!$this->canUpdateStatus($order, $request->status, $user)) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot update order to this status'
            ], 400);
        }

        $order->updateStatus($request->status, $request->note);

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => 'Order status updated successfully'
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, Order $order)
    {
        // Check authorization
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to cancel this order'
            ], 403);
        }

        // Check if order can be cancelled
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'error' => 'Order cannot be cancelled at this stage'
            ], 400);
        }

        $order->cancel($request->get('note', 'Order cancelled by buyer'));

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => 'Order cancelled successfully'
        ]);
    }

    /**
     * Confirm order
     */
    public function confirm(Request $request, Order $order)
    {
        // Check authorization
        if ($order->seller_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to confirm this order'
            ], 403);
        }

        // Check if order can be confirmed
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'Order cannot be confirmed at this stage'
            ], 400);
        }

        $order->confirm($request->get('note', 'Order confirmed by seller'));

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => 'Order confirmed successfully'
        ]);
    }

    /**
     * Ship order
     */
    public function ship(Request $request, Order $order)
    {
        // Check authorization
        if ($order->seller_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to ship this order'
            ], 403);
        }

        // Check if order can be shipped
        if ($order->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'error' => 'Order must be confirmed before shipping'
            ], 400);
        }

        $order->ship($request->get('note', 'Order shipped'));

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => 'Order shipped successfully'
        ]);
    }

    /**
     * Deliver order
     */
    public function deliver(Request $request, Order $order)
    {
        // Check authorization
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to mark this order as delivered'
            ], 403);
        }

        // Check if order can be delivered
        if ($order->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'error' => 'Order must be shipped before delivery confirmation'
            ], 400);
        }

        $order->deliver($request->get('note', 'Order delivered'));

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => 'Order marked as delivered successfully'
        ]);
    }

    /**
     * Check if user can update order to specific status
     */
    private function canUpdateStatus(Order $order, $status, User $user)
    {
        $currentStatus = $order->status;
        $isBuyer = $order->buyer_id === $user->id;
        $isSeller = $order->seller_id === $user->id;

        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
            'refunded' => []
        ];

        if (!in_array($status, $allowedTransitions[$currentStatus] ?? [])) {
            return false;
        }

        // Check role-based permissions
        switch ($status) {
            case 'confirmed':
            case 'shipped':
                return $isSeller;
            case 'delivered':
                return $isBuyer;
            case 'cancelled':
                return $isBuyer || $isSeller;
            default:
                return false;
        }
    }
}
