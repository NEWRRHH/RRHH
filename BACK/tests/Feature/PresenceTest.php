<?php

namespace Tests\Feature;

use App\Models\EventType;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Remote work requests and today's presence (remote / office / vacation):
 * the icon on every avatar and the extra lists of Reportes > Who's in.
 */
class PresenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $remote;
    private User $onVacation;
    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-17 10:00:00'); // Wednesday

        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
        $this->companyId = $company->id;

        [$this->remote, $this->onVacation] = Tenant::run($company->id, function () use ($company) {
            $role = Role::where('name', 'Empleado')->value('id');

            return [
                User::factory()->create(['company_id' => $company->id, 'role_id' => $role, 'vacation_days_total' => 22]),
                User::factory()->create(['company_id' => $company->id, 'role_id' => $role, 'vacation_days_total' => 22]),
            ];
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::clear();
        parent::tearDown();
    }

    private function type(string $code): int
    {
        return Tenant::run($this->companyId, fn () => EventType::idFor($code));
    }

    private function requestAndApprove(User $who, string $code): void
    {
        Sanctum::actingAs($who);
        // remote work has its own view (Teletrabajo), the rest goes through Vacaciones
        $id = $code === EventType::REMOTE_WORK
            ? $this->postJson('/api/remote-work', ['dates' => ['2026-06-17']])->assertOk()->json('event_ids.0')
            : $this->postJson('/api/timeoff/events', [
                'event_type_id' => $this->type($code),
                'start_date' => '2026-06-17', 'end_date' => '2026-06-17', 'all_day' => true,
            ])->assertOk()->json('event_id');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/timeoff/requests/{$id}/review", ['decision' => 'approved'])->assertOk();
    }

    public function test_fixed_work_mode(): void
    {
        // "solo teletrabajo": remote every day without asking, can't request it
        $this->remote->forceFill(['work_mode' => User::WORK_MODE_REMOTE])->save();
        // "solo oficina": never remote, can't request it either
        $this->onVacation->forceFill(['work_mode' => User::WORK_MODE_OFFICE])->save();

        Sanctum::actingAs($this->remote);
        $this->getJson('/api/user')->assertJsonPath('can_request_remote_work', false);
        $this->postJson('/api/remote-work', ['dates' => ['2026-06-18']])->assertStatus(422);

        Sanctum::actingAs($this->onVacation);
        $this->postJson('/api/remote-work', ['dates' => ['2026-06-18']])
            ->assertStatus(422)->assertJsonPath('message', 'Tu modalidad de trabajo es solo oficina: no puedes solicitar teletrabajo.');
        $this->postJson('/api/attendance/start')->assertOk();

        $presence = $this->getJson('/api/presence/today')->assertOk();
        $this->assertSame([$this->remote->id], $presence->json('remote'));   // without any request
        $this->assertSame([$this->onVacation->id], $presence->json('office'));

        // RRHH sets the modalidad from the employee form
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/employees/{$this->remote->id}")->assertOk()->assertJsonPath('employee.work_mode', 'remote');
    }

    public function test_remote_work_vacation_and_office_presence(): void
    {
        // "Teletrabajo" has its own view: not a type of the Vacaciones screen
        Sanctum::actingAs($this->remote);
        $types = collect($this->getJson('/api/timeoff/calendar?year=2026')->json('event_types'))->pluck('code');
        $this->assertNotContains(EventType::REMOTE_WORK, $types);
        $this->getJson('/api/user')->assertJsonPath('can_request_remote_work', true);

        $this->requestAndApprove($this->remote, EventType::REMOTE_WORK);
        $this->requestAndApprove($this->onVacation, EventType::VACATION);

        // the admin clocks in at the office; the remote worker clocks in from home
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/attendance/start')->assertOk();
        Sanctum::actingAs($this->remote);
        $this->postJson('/api/attendance/start')->assertOk();

        $presence = $this->getJson('/api/presence/today')->assertOk();
        $this->assertSame([$this->remote->id], $presence->json('remote'));
        $this->assertSame([$this->onVacation->id], $presence->json('vacation'));
        $this->assertSame([$this->admin->id], $presence->json('office'));

        // who's in (RRHH / admin): the two new lists
        Sanctum::actingAs($this->admin);
        $who = $this->getJson('/api/attendance/who-is-in')->assertOk();
        $this->assertSame([$this->remote->id], array_column($who->json('remote_rows'), 'user_id'));
        $this->assertTrue($who->json('remote_rows.0.working'));
        $this->assertSame([$this->onVacation->id], array_column($who->json('vacation_rows'), 'user_id'));
    }
}
