<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * Someone in the company clocked in or out, so "who is working today"
 * changed. Carries no personal data: dashboards just reload their counters.
 */
class CompanyAttendanceChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public int $companyId)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("company.{$this->companyId}.attendance");
    }

    public function broadcastAs(): string
    {
        return 'CompanyAttendanceChanged';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
