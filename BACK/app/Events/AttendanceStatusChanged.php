<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * The user clocked in, paused, resumed or clocked out.
 *
 * Sent on the user's own private channel so every open tab/device of that
 * user updates the clock-in pill and the status dots instantly.
 */
class AttendanceStatusChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public const WORKING = 'working';
    public const PAUSED = 'paused';
    public const IDLE = 'idle';

    public function __construct(public int $userId, public string $status)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'AttendanceStatusChanged';
    }

    public function broadcastWith(): array
    {
        return ['status' => $this->status];
    }
}
