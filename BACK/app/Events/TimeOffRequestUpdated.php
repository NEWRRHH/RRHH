<?php

namespace App\Events;

use App\Support\Tenancy\Tenant;
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
    /** Company the request belongs to: reviewers only hear their own company. */
    public ?int $companyId;

    public function __construct(int $requestId, int $requesterId, string $action)
    {
        $this->requestId = $requestId;
        $this->requesterId = $requesterId;
        $this->action = $action;
        $this->companyId = Tenant::id();
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("user.{$this->requesterId}")];
        if ($this->companyId !== null) {
            $channels[] = new PrivateChannel("company.{$this->companyId}.timeoff.reviewers");
        }

        return $channels;
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

