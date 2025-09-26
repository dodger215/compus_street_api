<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_id',
        'buyer_id',
        'seller_id',
        'subject',
        'last_message_at',
        'is_active'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_message_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get the item for this conversation
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the buyer in this conversation
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller in this conversation
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get messages for this conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get participants in this conversation
     */
    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_participants');
    }

    /**
     * Get the latest message
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * Get unread messages count
     */
    public function getUnreadMessagesCountAttribute()
    {
        return $this->messages()->where('is_read', false)->count();
    }

    /**
     * Get messages count
     */
    public function getMessagesCountAttribute()
    {
        return $this->messages()->count();
    }

    /**
     * Check if conversation is active
     */
    public function isActive()
    {
        return $this->is_active;
    }

    /**
     * Check if user is participant
     */
    public function hasParticipant($userId)
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Add participant to conversation
     */
    public function addParticipant($userId)
    {
        if (!$this->hasParticipant($userId)) {
            $this->participants()->attach($userId);
        }
    }

    /**
     * Remove participant from conversation
     */
    public function removeParticipant($userId)
    {
        $this->participants()->detach($userId);
    }

    /**
     * Mark conversation as read for user
     */
    public function markAsReadForUser($userId)
    {
        $this->messages()
            ->where('recipient_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Update last message timestamp
     */
    public function updateLastMessage()
    {
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Activate conversation
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate conversation
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Scope for active conversations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for user conversations
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('buyer_id', $userId)
              ->orWhere('seller_id', $userId);
        });
    }

    /**
     * Scope for item conversations
     */
    public function scopeForItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope for recent conversations
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('last_message_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for conversations with unread messages
     */
    public function scopeWithUnreadMessages($query, $userId)
    {
        return $query->whereHas('messages', function($q) use ($userId) {
            $q->where('recipient_id', $userId)
              ->where('is_read', false);
        });
    }
}
