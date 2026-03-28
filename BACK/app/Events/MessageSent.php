<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
// ShouldBroadcastNow broadcasts synchronously (no queue worker needed).
// This guarantees instant delivery to Reverb without requiring `queue:work`.
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a notification/message is created or updated.
 */
class MessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $conversation;
    public int $targetUserId;

    /**
     * Create a new event instance.
     */
    public function __construct($conversation, int $targetUserId)
    {
        $this->conversation = $conversation;
        $this->targetUserId = $targetUserId;
    }

    /**
     * Get the channels the event should broadcast on.
     * We'll broadcast on a private channel for the receiver so that only
     * the intended user receives the update.
     */
    public function broadcastOn()
    {
        // Always broadcast to the recipient of the current message,
        // not the historical receiver stored in the conversation row.
        return new PrivateChannel("user.{$this->targetUserId}");
    }

    /**
     * Short event name so the frontend can listen with '.MessageSent'.
     * Without this, Laravel broadcasts as 'App\Events\MessageSent'
     * which Echo would never match with .listen('MessageSent', ...).
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Data to broadcast (automatically serialized to JSON).
     */
    public function broadcastWith()
    {
        return [
            'conversation' => $this->conversation,
        ];
    }
}
