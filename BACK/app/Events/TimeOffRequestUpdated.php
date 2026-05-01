<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class TimeOffRequestUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public int $requestId;
    public int $requesterId;
    public string $action;

    public function __construct(int $requestId, int $requesterId, string $action)
    {
        $this->requestId = $requestId;
        $this->requesterId = $requesterId;
        $this->action = $action;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('timeoff.reviewers'),
            new PrivateChannel("user.{$this->requesterId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TimeOffRequestUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->requestId,
            'requester_id' => $this->requesterId,
            'action' => $this->action,
        ];
    }
}

