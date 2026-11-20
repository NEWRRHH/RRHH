<?php

namespace Tests\Feature;

use App\Mail\AttendanceSummaryMail;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\CompanySettings;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * - Vacation only within the current year (+ the days of next year the
 *   company allows, charged to the current year).
 * - Teletrabajo: own view, independent of the vacation days.
 * - Monthly attendance summary PDF (Documentos > Asistencia + email).
 */
class VacationRemoteWorkAndSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00'); // Wednesday

        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
        $this->companyId = $company->id;

        $this->employee = Tenant::run($company->id, fn () => User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', 'Empleado')->value('id'),
            'vacation_days_total' => 5,
            'dni' => '30111222A',
        ]));
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

    private function vacation(string $from, string $to)
    {
        return $this->postJson('/api/timeoff/events', [
            'event_type_id' => $this->type(EventType::VACATION),
            'start_date' => $from, 'end_date' => $to, 'all_day' => true,
        ]);
    }

    public function test_vacation_only_in_current_year_unless_company_allows_some_days(): void
    {
        Sanctum::actingAs($this->employee);
        $this->vacation('2027-01-04', '2027-01-05')->assertStatus(422)
            ->assertJsonPath('message', 'Solo puedes solicitar vacaciones del año en curso (2026).');

        CompanySettings::save($this->companyId, ['vacation_next_year_days' => 10]); // up to 10/01/2027
        $this->vacation('2027-01-11', '2027-01-11')->assertStatus(422);

        // Mon 04/01 - Thu 07/01: 4 working days of 2027, charged to 2026
        $id = $this->vacation('2027-01-04', '2027-01-07')->assertOk()->json('event_id');
        $this->assertSame(2026, (int) Tenant::run($this->companyId, fn () => Event::table()->where('id', $id)->value('allowance_year')));

        $calendar = $this->getJson('/api/timeoff/calendar?year=2027')->assertOk();
        $calendar->assertJsonPath('vacation.max_date', '2027-01-10')->assertJsonPath('vacation.allowance_year', 2026);
        $this->assertCount(4, $calendar->json("vacation.used_by_event.{$id}"));

        // only 1 day left this year
        $this->vacation('2026-12-21', '2026-12-22')->assertStatus(422)->assertJsonPath('available_days', 1);
        $this->getJson('/api/vacations/me')->assertJsonPath('remaining_vacation_days', 1);
    }

    public function test_without_vacation_days_other_absences_and_remote_work_still_work(): void
    {
        $this->employee->forceFill(['vacation_days_total' => 0])->save();
        Sanctum::actingAs($this->employee);

        $this->vacation('2026-11-20', '2026-11-20')->assertStatus(422)
            ->assertJsonPath('message', 'No tienes días de vacaciones asignados. Consulta con RRHH.');

        $this->postJson('/api/timeoff/events', [
            'event_type_id' => $this->type(EventType::PERMISSION),
            'description' => 'Médico',
            'start_date' => '2026-11-20', 'end_date' => '2026-11-20', 'all_day' => true,
        ])->assertOk();

        // remote work is not a type of Vacaciones any more
        $this->postJson('/api/timeoff/events', [
            'event_type_id' => $this->type(EventType::REMOTE_WORK),
            'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'all_day' => true,
        ])->assertStatus(422);

        // Teletrabajo view: Mon 23 + Tue 24 (one request) and Thu 26 (another one)
        $this->postJson('/api/remote-work', ['dates' => ['2026-11-26', '2026-11-23', '2026-11-24']])
            ->assertOk()->assertJsonPath('requests', 2)->assertJsonPath('days', 3);

        // weekend, past, the permission day and already requested days are refused
        $this->postJson('/api/remote-work', ['dates' => ['2026-11-21']])->assertStatus(422);
        $this->postJson('/api/remote-work', ['dates' => ['2026-11-17']])->assertStatus(422);
        $this->postJson('/api/remote-work', ['dates' => ['2026-11-20']])->assertStatus(422);
        $this->postJson('/api/remote-work', ['dates' => ['2026-11-24']])->assertStatus(422);

        $index = $this->getJson('/api/remote-work?year=2026')->assertOk();
        $index->assertJsonPath('can_request', true)->assertJsonCount(2, 'requests');
        $this->assertArrayHasKey('2026-11-20', $index->json('blocked'));

        // RRHH reviews it in Solicitudes; the employee can withdraw a pending one
        Sanctum::actingAs($this->admin);
        $codes = collect($this->getJson('/api/timeoff/requests?status=pending')->json('requests'))->pluck('event_type_code');
        $this->assertSame(2, $codes->filter(fn ($c) => $c === EventType::REMOTE_WORK)->count());

        Sanctum::actingAs($this->employee);
        $id = collect($index->json('requests'))->firstWhere('start_date', '2026-11-26')['id'];
        $this->deleteJson("/api/remote-work/{$id}")->assertOk();
        $this->assertCount(1, $this->getJson('/api/remote-work?year=2026')->json('requests'));
    }

    public function test_monthly_attendance_summary_pdf_document_and_email(): void
    {
        Mail::fake();
        Tenant::run($this->companyId, fn () => Attendance::table()->insert([
            'user_id' => $this->employee->id, 'date' => '2026-10-01', 'start_time' => '09:00:00',
            'end_time' => '17:00:00', 'end_date' => '2026-10-01', 'hours_worked' => '08:00:00', 'status' => 'fuera_trabajo',
            'created_at' => now(), 'updated_at' => now(),
        ]));

        // the scheduler (hourly) creates last month's summary for everybody and emails it
        $this->artisan('attendance:summaries')->assertSuccessful();
        Mail::assertQueued(AttendanceSummaryMail::class, 2);
        Mail::assertQueued(AttendanceSummaryMail::class, fn ($m) => $m->hasTo($this->employee->email) && $m->monthLabel === 'Octubre 2026');

        // next runs: nothing missing -> nothing generated, nobody emailed twice
        $this->artisan('attendance:summaries')->assertSuccessful();
        Mail::assertQueued(AttendanceSummaryMail::class, 2);

        // --force regenerates (replacing the file) without emailing again
        $this->artisan('attendance:summaries --month=2026-10 --force')->assertSuccessful();
        Mail::assertQueued(AttendanceSummaryMail::class, 2);
        $this->artisan('attendance:summaries --month=2026-11')->assertFailed(); // not finished yet

        Sanctum::actingAs($this->employee);
        $docs = $this->getJson('/api/documents?category=attendance')->assertOk()->json('documents');
        $this->assertCount(1, $docs);
        $this->assertSame('resumen-asistencia-2026-10.pdf', $docs[0]['original_name']);
        $this->getJson('/api/documents/unread')->assertJsonPath('by_category.attendance', 1);
        $download = $this->get("/api/documents/{$docs[0]['id']}/download")->assertOk();
        $this->assertStringStartsWith('%PDF', $download->streamedContent());
    }

    public function test_summary_email_can_be_disabled(): void
    {
        Mail::fake();
        CompanySettings::save($this->companyId, ['attendance_summary_email' => false]);
        $this->artisan('attendance:summaries')->assertSuccessful(); // previous month: October
        $this->assertSame(2, Document::withoutGlobalScopes()->where('category', 'attendance')->count());
        Mail::assertNothingQueued();
    }

    public function test_policies_settings(): void
    {
        Sanctum::actingAs($this->employee);
        $this->putJson('/api/settings/policies', [
            'vacation_next_year_days' => 15, 'attendance_summary_email' => true,
        ])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->putJson('/api/settings/policies', [
            'vacation_next_year_days' => 15, 'attendance_summary_email' => false,
        ])->assertOk()->assertJsonPath('settings.vacation_next_year_days', 15);
        $this->getJson('/api/settings/policies')->assertJsonPath('settings.attendance_summary_email', false);
    }

    public function test_remote_work_attendance_request_and_policies_permissions(): void
    {
        // the Empleado role starts with remote work and attendance requests, not policies
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/user')
            ->assertJsonPath('can_request_remote_work', true)
            ->assertJsonPath('can_request_attendance', true)
            ->assertJsonPath('can_manage_policies', false);

        // RRHH takes them away from the role
        $roleId = (int) $this->employee->role_id;
        $codes = ['remote_work.view', 'remote_work.request', 'attendance.request'];
        \Illuminate\Support\Facades\DB::table('role_permission')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', \Illuminate\Support\Facades\DB::table('permissions')->whereIn('code', $codes)->pluck('id'))
            ->delete();
        $employee = User::withoutGlobalScopes()->findOrFail($this->employee->id); // fresh permission cache
        Sanctum::actingAs($employee);

        $this->getJson('/api/user')->assertJsonPath('can_request_remote_work', false)->assertJsonPath('can_request_attendance', false);
        $this->getJson('/api/remote-work?year=2026')->assertForbidden();
        $this->postJson('/api/remote-work', ['dates' => ['2026-11-23']])->assertForbidden();
        $this->postJson('/api/attendance/requests/days', ['dates' => ['2026-11-17']])->assertForbidden();

        // a role with policies.manage (not admin) can edit the policies
        $policies = \Illuminate\Support\Facades\DB::table('permissions')->where('code', 'policies.manage')->value('id');
        \Illuminate\Support\Facades\DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $policies, 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs(User::withoutGlobalScopes()->findOrFail($this->employee->id));
        $this->putJson('/api/settings/policies', ['vacation_next_year_days' => 5, 'attendance_summary_email' => true])->assertOk();
    }
}
