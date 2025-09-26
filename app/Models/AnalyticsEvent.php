<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'item_id',
        'order_id',
        'event_type',
        'event_name',
        'properties',
        'session_id',
        'ip_address',
        'user_agent'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'array'
    ];

    /**
     * Get the user who triggered this event
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item related to this event
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the order related to this event
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get event type badge
     */
    public function getEventTypeBadgeAttribute()
    {
        $badges = [
            'page_view' => 'bg-blue-100 text-blue-800',
            'item_view' => 'bg-green-100 text-green-800',
            'search' => 'bg-purple-100 text-purple-800',
            'purchase' => 'bg-yellow-100 text-yellow-800',
            'click' => 'bg-orange-100 text-orange-800',
            'conversion' => 'bg-red-100 text-red-800'
        ];

        return $badges[$this->event_type] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get formatted event type
     */
    public function getFormattedEventTypeAttribute()
    {
        $types = [
            'page_view' => 'Page View',
            'item_view' => 'Item View',
            'search' => 'Search',
            'purchase' => 'Purchase',
            'click' => 'Click',
            'conversion' => 'Conversion'
        ];

        return $types[$this->event_type] ?? 'Event';
    }

    /**
     * Scope for event type
     */
    public function scopeEventType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for event name
     */
    public function scopeEventName($query, $name)
    {
        return $query->where('event_name', $name);
    }

    /**
     * Scope for user events
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for item events
     */
    public function scopeForItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope for order events
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope for session events
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for today's events
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for this week's events
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope for this month's events
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    /**
     * Track page view
     */
    public static function trackPageView($userId, $page, $properties = [])
    {
        return self::create([
            'user_id' => $userId,
            'event_type' => 'page_view',
            'event_name' => 'page_viewed',
            'properties' => array_merge($properties, ['page' => $page]),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Track item view
     */
    public static function trackItemView($userId, $itemId, $properties = [])
    {
        return self::create([
            'user_id' => $userId,
            'item_id' => $itemId,
            'event_type' => 'item_view',
            'event_name' => 'item_viewed',
            'properties' => $properties,
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Track search
     */
    public static function trackSearch($userId, $query, $resultsCount = 0, $properties = [])
    {
        return self::create([
            'user_id' => $userId,
            'event_type' => 'search',
            'event_name' => 'search_performed',
            'properties' => array_merge($properties, [
                'query' => $query,
                'results_count' => $resultsCount
            ]),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Track purchase
     */
    public static function trackPurchase($userId, $orderId, $amount, $properties = [])
    {
        return self::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'event_type' => 'purchase',
            'event_name' => 'purchase_completed',
            'properties' => array_merge($properties, ['amount' => $amount]),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Track conversion
     */
    public static function trackConversion($userId, $conversionType, $properties = [])
    {
        return self::create([
            'user_id' => $userId,
            'event_type' => 'conversion',
            'event_name' => 'conversion_achieved',
            'properties' => array_merge($properties, ['conversion_type' => $conversionType]),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
