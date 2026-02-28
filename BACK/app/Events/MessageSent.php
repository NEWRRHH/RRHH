<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
// we only need the contract interface; the other imports were pointing
// to non-existent classes which triggered the error seen in logs.
use Illuminate\Contracts\Broadcasting\ShouldBroadcast as ShouldBroadcastContract;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a notification/message is created or updated.
 */
class MessageSent implements ShouldBroadcastContract
{
    use InteractsWithSockets, SerializesModels;

    public $conversation;

    /**
     * Create a new event instance.
     */
    public function __construct($conversation)
    {
        $this->conversation = $conversation;
    }

    /**
     * Get the channels the event should broadcast on.
     * We'll broadcast on a private channel for the receiver so that only
     * the intended user receives the update.
     */
    public function broadcastOn()
    {
        // conversation object contains receiver_id; broadcast on their private channel
        $receiver = $this->conversation->receiver_id;
        return new PrivateChannel("user.{$receiver}");
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
