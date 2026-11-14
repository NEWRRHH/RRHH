<?php

namespace Tests\Feature;

use App\Events\AttendanceStatusChanged;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Clocking in/out is pushed over WebSocket to the user's own channel so their
 * other tabs and devices update the clock-in pill and status dots instantly.
 */
class AttendanceStatusBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::clear();
        parent::tearDown();
    }

    public function test_each_clock_action_broadcasts_the_new_status_to_the_user(): void
    {
        ['admin' => $user] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
        Sanctum::actingAs($user);
        // a Monday mid-morning: inside any default schedule
        $this->travelTo(now()->next('Monday')->setTime(11, 0));

        Event::fake([AttendanceStatusChanged::class]);

        $this->postJson('/api/attendance/start')->assertOk();
        $this->postJson('/api/attendance/pause')->assertOk();
        $this->postJson('/api/attendance/resume')->assertOk();
        $this->postJson('/api/attendance/stop')->assertOk();

        $sent = [];
        Event::assertDispatched(AttendanceStatusChanged::class, function (AttendanceStatusChanged $e) use ($user, &$sent) {
            $this->assertSame((int) $user->id, $e->userId);
            $this->assertSame("private-user.{$user->id}", $e->broadcastOn()->name);
            $sent[] = $e->status;

            return true;
        });
        $this->assertSame(['working', 'paused', 'working', 'idle'], $sent);

        // the day is closed: info says so, and a second clock-in is refused with a reason
        $this->getJson('/api/attendance/info')->assertOk()
            ->assertJsonPath('attendance', null)
            ->assertJsonPath('closed_today.start_time', '11:00:00');
        $this->postJson('/api/attendance/start')->assertStatus(422)
            ->assertJsonPath('message', 'Ya registraste entrada y salida hoy.');
    }

    public function test_pause_without_an_open_day_broadcasts_nothing(): void
    {
        ['admin' => $user] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
        Sanctum::actingAs($user);

        Event::fake([AttendanceStatusChanged::class]);

        $this->postJson('/api/attendance/pause')->assertStatus(422);
        Event::assertNotDispatched(AttendanceStatusChanged::class);
    }
}
