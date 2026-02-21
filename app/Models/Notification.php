<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'content', // legacy field, now used for JSON conversation
        'conversation', // json array of messages
        'read',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    protected $casts = [
        'conversation' => 'array',
    ];

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Scope messages involving the given user (either sender or receiver).
     */
    public function scopeInvolving($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
              ->orWhere('receiver_id', $userId);
        });
    }

    /**
     * Accessor for last message in conversation.
     */
    public function getLastMessageAttribute()
    {
        $conv = $this->conversation ?: [];
        return empty($conv) ? null : end($conv);
    }

    /**
     * Scope conversation between two users, ordered by timestamp.
     */
    public function scopeConversationWith($query, $userId, $otherId)
    {
        return $query->where(function ($q) use ($userId, $otherId) {
            $q->where('sender_id', $userId)->where('receiver_id', $otherId);
        })->orWhere(function ($q) use ($userId, $otherId) {
            $q->where('sender_id', $otherId)->where('receiver_id', $userId);
        })->orderBy('created_at', 'asc');
    }
}
