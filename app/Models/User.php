<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'year',
        'phone',
        'bio',
        'location',
        'avatar',
        'college_domain',
        'is_verified',
        'preferences',
        'stats',
        'last_login'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'is_verified' => 'boolean',
        'preferences' => 'array',
        'stats' => 'array',
    ];

    /**
     * Get user's listings
     */
    public function listings()
    {
        return $this->hasMany(Item::class, 'seller_id');
    }

    /**
     * Get user's orders as buyer
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    /**
     * Get user's orders as seller
     */
    public function sales()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    /**
     * Get user's reviews
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Get reviews about this user
     */
    public function receivedReviews()
    {
        return $this->hasMany(Review::class, 'target_id')->where('type', 'seller');
    }

    /**
     * Get user's messages
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get conversations where user is participant
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants');
    }

    /**
     * Get user's wishlist
     */
    public function wishlist()
    {
        return $this->belongsToMany(Item::class, 'wishlists');
    }

    /**
     * Get user's notifications
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get user's payments
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get user's analytics events
     */
    public function analyticsEvents()
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    /**
     * Get average rating
     */
    public function getAverageRatingAttribute()
    {
        $reviews = $this->receivedReviews();
        if ($reviews->count() === 0) {
            return 0;
        }
        
        return round($reviews->avg('rating'), 1);
    }

    /**
     * Get reviews count
     */
    public function getReviewsCountAttribute()
    {
        return $this->receivedReviews()->count();
    }

    /**
     * Get listings count
     */
    public function getListingsCountAttribute()
    {
        return $this->listings()->count();
    }

    /**
     * Get orders count
     */
    public function getOrdersCountAttribute()
    {
        return $this->orders()->count();
    }

    /**
     * Get sales count
     */
    public function getSalesCountAttribute()
    {
        return $this->sales()->count();
    }

    /**
     * Check if user has reviewed item
     */
    public function hasReviewedItem($itemId)
    {
        return $this->reviews()
            ->where('target_id', $itemId)
            ->where('type', 'item')
            ->exists();
    }

    /**
     * Check if user has reviewed seller
     */
    public function hasReviewedSeller($sellerId)
    {
        return $this->reviews()
            ->where('target_id', $sellerId)
            ->where('type', 'seller')
            ->exists();
    }

    /**
     * Get user's wishlist count
     */
    public function getWishlistCountAttribute()
    {
        return $this->wishlist()->count();
    }

    /**
     * Check if item is in wishlist
     */
    public function hasInWishlist($itemId)
    {
        return $this->wishlist()->where('item_id', $itemId)->exists();
    }

    /**
     * Add item to wishlist
     */
    public function addToWishlist($itemId)
    {
        if (!$this->hasInWishlist($itemId)) {
            $this->wishlist()->attach($itemId);
            return true;
        }
        return false;
    }

    /**
     * Remove item from wishlist
     */
    public function removeFromWishlist($itemId)
    {
        if ($this->hasInWishlist($itemId)) {
            $this->wishlist()->detach($itemId);
            return true;
        }
        return false;
    }

    /**
     * Get user's unread notifications count
     */
    public function getUnreadNotificationsCountAttribute()
    {
        return $this->notifications()->where('read_at', null)->count();
    }

    /**
     * Get user's unread messages count
     */
    public function getUnreadMessagesCountAttribute()
    {
        return $this->conversations()
            ->whereHas('messages', function($query) {
                $query->where('sender_id', '!=', $this->id)
                      ->where('read_at', null);
            })
            ->count();
    }
}
