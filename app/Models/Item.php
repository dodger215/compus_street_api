<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'category',
        'subcategory',
        'price',
        'condition',
        'location',
        'images',
        'is_premium',
        'is_available',
        'views_count',
        'seller_id',
        'status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'images' => 'array',
        'is_premium' => 'boolean',
        'is_available' => 'boolean',
        'price' => 'decimal:2',
        'views_count' => 'integer'
    ];

    /**
     * Get the seller of the item
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get orders for this item
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get reviews for this item
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'target_id')->where('type', 'item');
    }

    /**
     * Get conversations about this item
     */
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get users who have this item in wishlist
     */
    public function wishlistedBy()
    {
        return $this->belongsToMany(User::class, 'wishlists');
    }

    /**
     * Get analytics events for this item
     */
    public function analyticsEvents()
    {
        return $this->hasMany(AnalyticsEvent::class, 'item_id');
    }

    /**
     * Get average rating
     */
    public function getAverageRatingAttribute()
    {
        $reviews = $this->reviews();
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
        return $this->reviews()->count();
    }

    /**
     * Get orders count
     */
    public function getOrdersCountAttribute()
    {
        return $this->orders()->count();
    }

    /**
     * Get wishlist count
     */
    public function getWishlistCountAttribute()
    {
        return $this->wishlistedBy()->count();
    }

    /**
     * Get main image
     */
    public function getMainImageAttribute()
    {
        $images = $this->images;
        return !empty($images) ? $images[0] : null;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute()
    {
        return 'GH₵' . number_format((float)$this->price, 2);
    }

    /**
     * Get status badge
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'available' => 'bg-green-100 text-green-800',
            'sold' => 'bg-gray-100 text-gray-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'draft' => 'bg-blue-100 text-blue-800'
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get condition badge
     */
    public function getConditionBadgeAttribute()
    {
        $badges = [
            'new' => 'bg-green-100 text-green-800',
            'like_new' => 'bg-blue-100 text-blue-800',
            'good' => 'bg-yellow-100 text-yellow-800',
            'fair' => 'bg-orange-100 text-orange-800',
            'poor' => 'bg-red-100 text-red-800'
        ];

        return $badges[$this->condition] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get formatted condition
     */
    public function getFormattedConditionAttribute()
    {
        $conditions = [
            'new' => 'New',
            'like_new' => 'Like New',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor'
        ];

        return $conditions[$this->condition] ?? 'Unknown';
    }

    /**
     * Get formatted category
     */
    public function getFormattedCategoryAttribute()
    {
        $categories = [
            'academic_books' => 'Academic Books',
            'electronics' => 'Electronics',
            'stationery' => 'Stationery & Supplies',
            'transportation' => 'Transportation',
            'furniture' => 'Furniture',
            'clothing' => 'Clothing',
            'sports' => 'Sports & Fitness',
            'other' => 'Other'
        ];

        return $categories[$this->category] ?? 'Other';
    }

    /**
     * Increment views count
     */
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    /**
     * Check if item is available
     */
    public function isAvailable()
    {
        return $this->is_available && $this->status === 'available';
    }

    /**
     * Check if item is premium
     */
    public function isPremium()
    {
        return $this->is_premium;
    }

    /**
     * Mark as sold
     */
    public function markAsSold()
    {
        $this->update([
            'status' => 'sold',
            'is_available' => false
        ]);
    }

    /**
     * Mark as available
     */
    public function markAsAvailable()
    {
        $this->update([
            'status' => 'available',
            'is_available' => true
        ]);
    }

    /**
     * Scope for available items
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                    ->where('status', 'available');
    }

    /**
     * Scope for premium items
     */
    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    /**
     * Scope for category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for price range
     */
    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Scope for condition
     */
    public function scopeCondition($query, $condition)
    {
        return $query->where('condition', $condition);
    }

    /**
     * Scope for location
     */
    public function scopeLocation($query, $location)
    {
        return $query->where('location', 'like', '%' . $location . '%');
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%')
              ->orWhere('category', 'like', '%' . $search . '%');
        });
    }
}
