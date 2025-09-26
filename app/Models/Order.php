<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'buyer_id',
        'seller_id',
        'item_id',
        'item_title',
        'item_price',
        'quantity',
        'total_amount',
        'status',
        'payment_status',
        'payment_reference',
        'shipping_address',
        'notes',
        'timeline'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'item_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'quantity' => 'integer',
        'timeline' => 'array'
    ];

    /**
     * Get the buyer of the order
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller of the order
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the item for this order
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get payments for this order
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get reviews for this order
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'order_id');
    }

    /**
     * Get conversation for this order
     */
    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Get analytics events for this order
     */
    public function analyticsEvents()
    {
        return $this->hasMany(AnalyticsEvent::class, 'order_id');
    }

    /**
     * Get status badge
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'shipped' => 'bg-purple-100 text-purple-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-gray-100 text-gray-800'
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get payment status badge
     */
    public function getPaymentStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'paid' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-gray-100 text-gray-800'
        ];

        return $badges[$this->payment_status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute()
    {
        $statuses = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded'
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    /**
     * Get formatted payment status
     */
    public function getFormattedPaymentStatusAttribute()
    {
        $statuses = [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded'
        ];

        return $statuses[$this->payment_status] ?? 'Unknown';
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAttribute()
    {
        return 'GH₵' . number_format((float)$this->total_amount, 2);
    }

    /**
     * Get formatted item price
     */
    public function getFormattedItemPriceAttribute()
    {
        return 'GH₵' . number_format((float)$this->item_price, 2);
    }

    /**
     * Check if order is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if order is confirmed
     */
    public function isConfirmed()
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if order is shipped
     */
    public function isShipped()
    {
        return $this->status === 'shipped';
    }

    /**
     * Check if order is delivered
     */
    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if payment is pending
     */
    public function isPaymentPending()
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if payment is paid
     */
    public function isPaymentPaid()
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if payment failed
     */
    public function isPaymentFailed()
    {
        return $this->payment_status === 'failed';
    }

    /**
     * Update order status
     */
    public function updateStatus($status, $note = '')
    {
        $this->update(['status' => $status]);
        
        // Add to timeline
        $timeline = $this->timeline ?? [];
        $timeline[] = [
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'note' => $note
        ];
        
        $this->update(['timeline' => $timeline]);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($status, $reference = null)
    {
        $this->update([
            'payment_status' => $status,
            'payment_reference' => $reference
        ]);
    }

    /**
     * Confirm order
     */
    public function confirm($note = 'Order confirmed by seller')
    {
        $this->updateStatus('confirmed', $note);
    }

    /**
     * Ship order
     */
    public function ship($note = 'Order shipped')
    {
        $this->updateStatus('shipped', $note);
    }

    /**
     * Deliver order
     */
    public function deliver($note = 'Order delivered')
    {
        $this->updateStatus('delivered', $note);
    }

    /**
     * Cancel order
     */
    public function cancel($note = 'Order cancelled')
    {
        $this->updateStatus('cancelled', $note);
    }

    /**
     * Refund order
     */
    public function refund($note = 'Order refunded')
    {
        $this->updateStatus('refunded', $note);
        $this->updatePaymentStatus('refunded');
    }

    /**
     * Scope for buyer orders
     */
    public function scopeForBuyer($query, $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Scope for seller orders
     */
    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope for status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for payment status
     */
    public function scopePaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
