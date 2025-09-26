<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reviewer_id',
        'target_id',
        'order_id',
        'type',
        'rating',
        'title',
        'comment',
        'is_helpful',
        'is_reported',
        'reported_reason',
        'status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'integer',
        'is_helpful' => 'boolean',
        'is_reported' => 'boolean'
    ];

    /**
     * Get the reviewer
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Get the target (item or seller being reviewed)
     */
    public function target()
    {
        if ($this->type === 'item') {
            return $this->belongsTo(Item::class, 'target_id');
        }
        return $this->belongsTo(User::class, 'target_id');
    }

    /**
     * Get the order for this review
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get rating badge
     */
    public function getRatingBadgeAttribute()
    {
        $badges = [
            1 => 'bg-red-100 text-red-800',
            2 => 'bg-orange-100 text-orange-800',
            3 => 'bg-yellow-100 text-yellow-800',
            4 => 'bg-blue-100 text-blue-800',
            5 => 'bg-green-100 text-green-800'
        ];

        return $badges[$this->rating] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get status badge
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'reported' => 'bg-orange-100 text-orange-800'
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get formatted rating
     */
    public function getFormattedRatingAttribute()
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Check if review is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if review is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if review is reported
     */
    public function isReported()
    {
        return $this->is_reported;
    }

    /**
     * Check if review is helpful
     */
    public function isHelpful()
    {
        return $this->is_helpful;
    }

    /**
     * Scope for approved reviews
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for pending reviews
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for reported reviews
     */
    public function scopeReported($query)
    {
        return $query->where('is_reported', true);
    }

    /**
     * Scope for item reviews
     */
    public function scopeForItems($query)
    {
        return $query->where('type', 'item');
    }

    /**
     * Scope for seller reviews
     */
    public function scopeForSellers($query)
    {
        return $query->where('type', 'seller');
    }

    /**
     * Scope for rating
     */
    public function scopeRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope for helpful reviews
     */
    public function scopeHelpful($query)
    {
        return $query->where('is_helpful', true);
    }
}
