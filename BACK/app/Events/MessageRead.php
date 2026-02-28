<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a user reads messages in a conversation.
 */
class MessageRead implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $conversation;
    public int $targetUserId;
    public int $readerId;

    /**
     * @param mixed $conversation Conversation row with updated message read flags.
     * @param int $targetUserId User who should receive the read receipt.
     * @param int $readerId User who opened/read the conversation.
     */
    public function __construct($conversation, int $targetUserId, int $readerId)
    {
        $this->conversation = $conversation;
        $this->targetUserId = $targetUserId;
        $this->readerId = $readerId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("user.{$this->targetUserId}");
    }

    public function broadcastAs(): string
    {
        return 'MessageRead';
    }

    public function broadcastWith()
    {
        return [
            'conversation' => $this->conversation,
            'reader_id' => $this->readerId,
        ];
    }
}
