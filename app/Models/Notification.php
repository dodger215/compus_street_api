<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
        'sent_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime'
    ];

    /**
     * Get the user who owns this notification
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get type badge
     */
    public function getTypeBadgeAttribute()
    {
        $badges = [
            'order' => 'bg-blue-100 text-blue-800',
            'payment' => 'bg-green-100 text-green-800',
            'message' => 'bg-purple-100 text-purple-800',
            'review' => 'bg-yellow-100 text-yellow-800',
            'system' => 'bg-gray-100 text-gray-800',
            'security' => 'bg-red-100 text-red-800'
        ];

        return $badges[$this->type] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get formatted type
     */
    public function getFormattedTypeAttribute()
    {
        $types = [
            'order' => 'Order Update',
            'payment' => 'Payment',
            'message' => 'New Message',
            'review' => 'Review',
            'system' => 'System',
            'security' => 'Security'
        ];

        return $types[$this->type] ?? 'Notification';
    }

    /**
     * Check if notification is read
     */
    public function isRead()
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification is unread
     */
    public function isUnread()
    {
        return is_null($this->read_at);
    }

    /**
     * Check if notification is sent
     */
    public function isSent()
    {
        return !is_null($this->sent_at);
    }

    /**
     * Mark as read
     */
    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark as unread
     */
    public function markAsUnread()
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Mark as sent
     */
    public function markAsSent()
    {
        $this->update(['sent_at' => now()]);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    /**
     * Scope for unsent notifications
     */
    public function scopeUnsent($query)
    {
        return $query->whereNull('sent_at');
    }

    /**
     * Scope for user notifications
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for notification type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for recent notifications
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Create notification for user
     */
    public static function createForUser($userId, $type, $title, $message, $data = [])
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Create order notification
     */
    public static function createOrderNotification($userId, $order, $action)
    {
        $messages = [
            'created' => 'New order created',
            'confirmed' => 'Order confirmed by seller',
            'shipped' => 'Order has been shipped',
            'delivered' => 'Order delivered successfully',
            'cancelled' => 'Order cancelled'
        ];

        return self::createForUser(
            $userId,
            'order',
            'Order Update',
            $messages[$action] ?? 'Order status updated',
            ['order_id' => $order->id, 'action' => $action]
        );
    }

    /**
     * Create payment notification
     */
    public static function createPaymentNotification($userId, $payment, $action)
    {
        $messages = [
            'success' => 'Payment successful',
            'failed' => 'Payment failed',
            'refunded' => 'Payment refunded'
        ];

        return self::createForUser(
            $userId,
            'payment',
            'Payment Update',
            $messages[$action] ?? 'Payment status updated',
            ['payment_id' => $payment->id, 'action' => $action]
        );
    }

    /**
     * Create message notification
     */
    public static function createMessageNotification($userId, $message)
    {
        return self::createForUser(
            $userId,
            'message',
            'New Message',
            'You have a new message',
            ['message_id' => $message->id, 'sender_id' => $message->sender_id]
        );
    }
}
