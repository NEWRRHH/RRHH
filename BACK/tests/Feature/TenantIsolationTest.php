<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use App\Support\DefaultRoles;
use App\Support\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Company A must never see or touch company B's data, and everything a user
 * creates must land in their own company.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->makeCompany('alpha');
        $this->b = $this->makeCompany('beta');
    }

    protected function tearDown(): void
    {
        Tenant::clear();
        parent::tearDown();
    }

    /**
     * A company with roles, a team, users and one row of each kind of data.
     */
    private function makeCompany(string $slug): array
    {
        $company = Company::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Company::STATUS_ACTIVE]);
        $roles = DefaultRoles::ensure($company->id);

        return Tenant::run($company->id, function () use ($company, $roles, $slug) {
            $team = Team::create(['name' => 'Equipo ' . $slug]);
            $admin = $this->makeUser($company, $roles['admin'], $team, "admin@{$slug}.test");
            $employee = $this->makeUser($company, $roles['employee'], $team, "emp@{$slug}.test");
            $schedule = Schedule::create(['start_time' => '09:00:00', 'end_time' => '18:00:00', 'days' => json_encode(['L', 'M', 'X', 'J', 'V'])]);
            $vacation = EventType::create(['name' => 'Vacaciones', 'code' => EventType::VACATION]);
            $event = Event::create([
                'title' => 'Vacaciones ' . $slug,
                'start_at' => now()->addDays(10)->startOfDay(),
                'end_at' => now()->addDays(12)->endOfDay(),
                'user_id' => $employee->id,
                'event_type_id' => $vacation->id,
                'all_day' => true,
                'approval_status' => 'pending',
            ]);
            $post = Post::create(['title' => 'Comunicado ' . $slug, 'body' => 'Hola', 'user_id' => $admin->id, 'status' => 'published', 'audience_scope' => 'all']);
            $document = Document::create(['description' => 'Doc ' . $slug, 'user_id' => $employee->id, 'filename' => "documents/x/{$slug}.pdf", 'category' => 'payroll']);

            return compact('company', 'roles', 'team', 'admin', 'employee', 'schedule', 'vacation', 'event', 'post', 'document');
        });
    }

    private function makeUser(Company $company, int $roleId, Team $team, string $email): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'email' => $email,
        ]);
    }

    private function actingAsAdminOf(array $tenant): void
    {
        Sanctum::actingAs($tenant['admin']);
    }

    // ---------------------------------------------------------------- reads

    public function test_employee_list_only_contains_own_company(): void
    {
        $this->actingAsAdminOf($this->a);

        $emails = collect($this->getJson('/api/employees')->assertOk()->json('employees'))->pluck('email');

        $this->assertEqualsCanonicalizing(['admin@alpha.test', 'emp@alpha.test'], $emails->all());
    }

    public function test_other_company_employee_is_not_found(): void
    {
        $this->actingAsAdminOf($this->a);
        $foreign = $this->b['employee']->id;

        $this->getJson("/api/employees/{$foreign}")->assertNotFound();
        $this->getJson("/api/employees/{$foreign}/attendance-month")->assertNotFound();
        $this->getJson("/api/employees/{$foreign}/documents")->assertNotFound();
        $this->putJson("/api/employees/{$foreign}", ['name' => 'x', 'email' => 'emp@beta.test'])->assertNotFound();
        $this->deleteJson("/api/employees/{$foreign}")->assertNotFound();

        $this->assertNotNull(User::withoutGlobalScopes()->find($foreign));
    }

    public function test_settings_lists_are_scoped(): void
    {
        $this->actingAsAdminOf($this->a);

        $teams = collect($this->getJson('/api/settings/teams')->assertOk()->json('teams'))->pluck('name');
        $this->assertSame(['Equipo alpha'], $teams->all());

        $scheduleIds = collect($this->getJson('/api/settings/schedules')->assertOk()->json('schedules'))->pluck('id');
        $this->assertSame([$this->a['schedule']->id], $scheduleIds->all());

        $roles = collect($this->getJson('/api/permissions/catalog')->assertOk()->json('roles'));
        $this->assertTrue($roles->every(fn ($r) => in_array($r['id'], array_values($this->a['roles']), true)));
        $this->assertCount(3, $roles);
    }

    public function test_requests_announcements_and_calendar_are_scoped(): void
    {
        $this->actingAsAdminOf($this->a);

        $requests = collect($this->getJson('/api/timeoff/requests?status=all')->assertOk()->json('requests'))->pluck('id');
        $this->assertSame([$this->a['event']->id], $requests->all());

        $posts = collect($this->getJson('/api/announcements')->assertOk()->json())->pluck('title');
        $this->assertSame(['Comunicado alpha'], $posts->all());

        $types = collect($this->getJson('/api/timeoff/calendar')->assertOk()->json('event_types'))->pluck('id');
        $this->assertSame([$this->a['vacation']->id], $types->all());

        $chatUsers = collect($this->getJson('/api/notifications/users')->assertOk()->json())->pluck('id');
        $this->assertNotContains($this->b['admin']->id, $chatUsers->all());
        $this->assertNotContains($this->b['employee']->id, $chatUsers->all());
    }

    // --------------------------------------------------------------- writes

    public function test_cannot_act_on_other_company_records(): void
    {
        $this->actingAsAdminOf($this->a);

        $this->putJson("/api/settings/teams/{$this->b['team']->id}", ['name' => 'Hack'])->assertNotFound();
        $this->deleteJson("/api/settings/schedules/{$this->b['schedule']->id}")->assertNotFound();
        $this->postJson("/api/timeoff/requests/{$this->b['event']->id}/review", ['decision' => 'approved'])->assertNotFound();
        $this->putJson("/api/roles/{$this->b['roles']['hr']}/permissions", ['permission_codes' => []])->assertNotFound();
        $this->deleteJson("/api/roles/{$this->b['roles']['hr']}")->assertNotFound();
        $this->postJson("/api/announcements/{$this->b['post']->id}/read")->assertNotFound();
        $this->getJson("/api/documents/{$this->b['document']->id}/download")->assertNotFound();

        $this->assertSame('Equipo beta', Team::withoutGlobalScopes()->find($this->b['team']->id)->name);
        $this->assertSame('pending', Event::withoutGlobalScopes()->find($this->b['event']->id)->approval_status);
    }

    public function test_cannot_reference_other_company_ids(): void
    {
        $this->actingAsAdminOf($this->a);
        $base = ['name' => 'Nuevo', 'password' => 'Nuevo1234!', 'password_confirmation' => 'Nuevo1234!'];

        $this->postJson('/api/employees', $base + ['email' => 'n1@alpha.test', 'team_id' => $this->b['team']->id])
            ->assertStatus(422)->assertJsonValidationErrors('team_id');
        $this->postJson('/api/employees', $base + ['email' => 'n2@alpha.test', 'role_id' => $this->b['roles']['employee']])
            ->assertStatus(422)->assertJsonValidationErrors('role_id');
        $this->postJson('/api/employees', $base + ['email' => 'n3@alpha.test', 'schedule_template_ids' => [$this->b['schedule']->id]])
            ->assertStatus(422)->assertJsonValidationErrors('schedule_template_ids.0');
        $this->postJson('/api/notifications/send', ['receiver_id' => $this->b['employee']->id, 'content' => 'hola'])
            ->assertStatus(422)->assertJsonValidationErrors('receiver_id');
        $this->postJson('/api/announcements', ['title' => 't', 'body' => 'b', 'scope' => 'team', 'team_id' => $this->b['team']->id])
            ->assertStatus(422)->assertJsonValidationErrors('team_id');
    }

    public function test_names_are_unique_per_company_only(): void
    {
        $this->actingAsAdminOf($this->a);

        // "Equipo beta" / "RRHH" exist in company B but not as duplicates in A
        $this->postJson('/api/settings/teams', ['name' => 'Equipo beta'])->assertCreated();
        $this->postJson('/api/settings/teams', ['name' => 'Equipo alpha'])->assertStatus(422);
        $this->postJson('/api/roles', ['name' => 'RRHH'])->assertStatus(422);
        $this->postJson('/api/roles', ['name' => 'IT'])->assertCreated();
    }

    public function test_created_records_belong_to_the_user_company(): void
    {
        $this->actingAsAdminOf($this->a);
        $companyA = $this->a['company']->id;

        $teamId = $this->postJson('/api/settings/teams', ['name' => 'Nuevo equipo'])->assertCreated()->json('team.id');
        $this->assertSame($companyA, Team::withoutGlobalScopes()->find($teamId)->company_id);

        $roleId = $this->postJson('/api/roles', ['name' => 'Ventas'])->assertCreated()->json('role.id');
        $this->assertSame($companyA, Role::withoutGlobalScopes()->find($roleId)->company_id);

        $employeeId = $this->postJson('/api/employees', [
            'name' => 'Nuevo', 'email' => 'nuevo@alpha.test', 'password' => 'Nuevo1234!', 'password_confirmation' => 'Nuevo1234!',
            'role_id' => $this->a['roles']['employee'],
        ])->assertCreated()->json('employee.id');
        $this->assertSame($companyA, User::withoutGlobalScopes()->find($employeeId)->company_id);

        $scheduleId = $this->postJson('/api/settings/schedules', ['start_time' => '08:00', 'end_time' => '15:00', 'days' => ['L', 'M']])
            ->assertCreated()->json('schedule.id');
        $this->assertSame($companyA, Schedule::withoutGlobalScopes()->find($scheduleId)->company_id);

        $this->postJson('/api/notifications/send', ['receiver_id' => $this->a['employee']->id, 'content' => 'hola'])->assertCreated();
        $this->assertSame($companyA, Notification::withoutGlobalScopes()->latest('id')->first()->company_id);

        $postId = $this->postJson('/api/announcements', ['title' => 't', 'body' => 'b', 'scope' => 'all'])->assertOk()->json('id');
        $this->assertSame($companyA, Post::withoutGlobalScopes()->find($postId)->company_id);

        $this->postJson('/api/attendance/start')->assertOk();
        $this->assertSame(
            [$companyA],
            \App\Models\Attendance::withoutGlobalScopes()->where('user_id', $this->a['admin']->id)->pluck('company_id')->unique()->values()->all(),
        );
    }

    // ------------------------------------------------------- access control

    public function test_suspended_company_is_locked_out(): void
    {
        $this->b['company']->update(['status' => Company::STATUS_SUSPENDED]);

        Sanctum::actingAs($this->b['admin']);
        $this->getJson('/api/user')->assertForbidden();

        $this->postJson('/api/login', ['email' => 'admin@beta.test', 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/login', ['email' => 'admin@alpha.test', 'password' => 'password'])->assertOk();
    }

    public function test_super_admin_sees_every_company_but_cannot_write_tenant_data(): void
    {
        $super = User::factory()->create(['company_id' => null, 'role_id' => null]);
        $super->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($super);

        $emails = collect($this->getJson('/api/employees')->assertOk()->json('employees'))->pluck('email');
        $this->assertContains('admin@alpha.test', $emails->all());
        $this->assertContains('admin@beta.test', $emails->all());

        $this->postJson('/api/settings/teams', ['name' => 'X'])->assertForbidden();
        $this->getJson('/api/user')->assertOk()->assertJson(['is_super_admin' => true]);
    }

    public function test_reviewer_channel_is_per_company(): void
    {
        // RRHH of company A may listen to A's reviewers channel only.
        $hr = Tenant::run($this->a['company']->id, fn () => $this->makeUser($this->a['company'], $this->a['roles']['hr'], $this->a['team'], 'hr@alpha.test'));
        $a = $this->a['company']->id;
        $b = $this->b['company']->id;

        $this->assertTrue($this->canJoinReviewers($hr, $a));
        $this->assertFalse($this->canJoinReviewers($hr, $b));
        $this->assertFalse($this->canJoinReviewers($this->a['employee'], $a));
    }

    public function test_attendance_channel_is_per_company(): void
    {
        // any employee hears their own company's clock-in pings, never another's
        $callback = app(\Illuminate\Contracts\Broadcasting\Broadcaster::class)
            ->getChannels()
            ->get('company.{companyId}.attendance');

        $this->assertTrue((bool) $callback($this->a['employee'], (string) $this->a['company']->id));
        $this->assertFalse((bool) $callback($this->a['employee'], (string) $this->b['company']->id));
    }

    private function canJoinReviewers(User $user, int $companyId): bool
    {
        $callback = app(\Illuminate\Contracts\Broadcasting\Broadcaster::class)
            ->getChannels()
            ->get('company.{companyId}.timeoff.reviewers');

        return (bool) $callback($user, (string) $companyId);
    }
}
