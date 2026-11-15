<?php

namespace Tests\Feature;

use App\Events\TimeOffRequestUpdated;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attendance requests for several absence days (Reportes > Ausencias):
 * RRHH accepts some days and rejects others. Plus the request rules of
 * the Vacaciones screen (title = type, required descriptions, no "Fichaje").
 */
class AttendanceRequestDaysTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-17 10:00:00'); // Wednesday
        EventFacade::fake([TimeOffRequestUpdated::class]);

        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
        $this->companyId = $company->id;

        $this->employee = Tenant::run($company->id, fn () => User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', 'Empleado')->value('id'),
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::clear();
        parent::tearDown();
    }

    private function typeId(string $code): int
    {
        return Tenant::run($this->companyId, fn () => EventType::idFor($code));
    }

    public function test_request_several_days_and_review_them_one_by_one(): void
    {
        Sanctum::actingAs($this->employee);
        $res = $this->postJson('/api/attendance/requests/days', ['dates' => ['2026-06-15', '2026-06-16', '2026-06-20']])
            ->assertOk()
            ->assertJsonPath('days', ['2026-06-15', '2026-06-16'])
            ->assertJsonPath('skipped.0.reason', 'futuro');
        $requestId = $res->json('request_id');

        // the same days can't be requested twice while pending
        $this->postJson('/api/attendance/requests/days', ['dates' => ['2026-06-15']])->assertStatus(422);
        $this->postJson('/api/attendance/requests', ['date' => '2026-06-16'])->assertStatus(422);

        // RRHH sees the days and accepts only the 15th
        Sanctum::actingAs($this->admin);
        $request = collect($this->getJson('/api/timeoff/requests?status=pending')->assertOk()->json('requests'))
            ->firstWhere('id', $requestId);
        $this->assertCount(2, $request['request_days']);
        $this->assertSame('attendance_request', $request['event_type_code']);

        $this->postJson("/api/timeoff/requests/{$requestId}/review", ['decision' => 'approved', 'approved_dates' => []])
            ->assertStatus(422);
        $this->postJson("/api/timeoff/requests/{$requestId}/review", [
            'decision' => 'approved',
            'approved_dates' => ['2026-06-15'],
            'comment' => 'El 16 estuviste de licencia',
        ])->assertOk()->assertJsonPath('approved_dates', ['2026-06-15']);

        $days = collect(json_decode(Event::withoutGlobalScopes()->find($requestId)->request_days, true))->keyBy('date');
        $this->assertSame('approved', $days['2026-06-15']['status']);
        $this->assertSame('rejected', $days['2026-06-16']['status']);

        $attendances = Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $this->assertSame(['2026-06-15'], $attendances);

        // reviewed: nothing left to decide
        $this->postJson("/api/timeoff/requests/{$requestId}/review", ['decision' => 'approved'])->assertStatus(422);
    }

    public function test_vacation_screen_request_rules(): void
    {
        Sanctum::actingAs($this->admin);
        $base = ['start_date' => '2026-06-22', 'end_date' => '2026-06-22', 'all_day' => true];

        // "Fichaje" is not requested from here
        $this->postJson('/api/timeoff/events', $base + ['event_type_id' => $this->typeId(EventType::ATTENDANCE_REQUEST)])
            ->assertStatus(422);
        $types = collect($this->getJson('/api/timeoff/calendar?year=2026')->assertOk()->json('event_types'))->pluck('code');
        $this->assertNotContains('attendance_request', $types);

        // Permiso / General need a description
        $this->postJson('/api/timeoff/events', $base + ['event_type_id' => $this->typeId(EventType::PERMISSION)])
            ->assertStatus(422);

        // the title is always the event type
        $id = $this->postJson('/api/timeoff/events', $base + [
            'event_type_id' => $this->typeId(EventType::GENERAL),
            'title' => 'lo que sea',
            'description' => 'Trámite en el banco',
        ])->assertOk()->json('event_id');
        $this->assertSame('General', Event::withoutGlobalScopes()->find($id)->title);

        // Reunión: description optional
        $this->postJson('/api/timeoff/events', $base + ['event_type_id' => $this->typeId(EventType::MEETING)])->assertOk();
    }
}
