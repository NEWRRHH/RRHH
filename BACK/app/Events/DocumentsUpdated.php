<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * New documents for the user (e.g. a payslip dropped in their nominas
 * folder) or the user saw them: updates the badge of the Documentos menu
 * and the dashboard card in every open tab/device, in real time.
 */
class DocumentsUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public int $userId, public int $unread, public int $created = 0)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'DocumentsUpdated';
    }

    public function broadcastWith(): array
    {
        return ['unread' => $this->unread, 'created' => $this->created];
    }
}
