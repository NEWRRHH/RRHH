<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ErrorLog;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\ErrorLogRecorder;
use App\Support\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private Company $acme;
    private User $acmeAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = User::factory()->create(['company_id' => null, 'role_id' => null, 'email' => 'super@test.test']);
        $this->super->forceFill(['is_super_admin' => true])->save();

        ['company' => $this->acme, 'admin' => $this->acmeAdmin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana Admin', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
    }

    protected function tearDown(): void
    {
        Tenant::clear();
        parent::tearDown();
    }

    /** Make the next requests authenticate only with the given bearer token. */
    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_only_the_super_admin_can_use_the_panel(): void
    {
        Sanctum::actingAs($this->acmeAdmin);
        foreach (['/api/admin/dashboard', '/api/admin/companies', '/api/admin/users', '/api/admin/errors', '/api/admin/audit'] as $url) {
            $this->getJson($url)->assertForbidden();
        }

        Sanctum::actingAs($this->super);
        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('totals.companies', 1)
            ->assertJsonPath('totals.employees', 1)
            ->assertJsonStructure(['charts' => ['companies_per_month', 'employees_by_company', 'activity_per_day', 'errors_per_day'], 'latest_errors']);
    }

    public function test_company_lifecycle(): void
    {
        Sanctum::actingAs($this->super);

        $id = $this->postJson('/api/admin/companies', [
            'company_name' => 'Beta SL', 'plan' => 'pro',
            'admin_name' => 'Bea', 'admin_email' => 'bea@beta.test', 'admin_password' => 'Secreta123!',
        ])->assertCreated()->json('company.id');

        $this->getJson('/api/admin/companies?q=beta')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.users_count', 1);
        $this->getJson("/api/admin/companies/{$id}")->assertOk()
            ->assertJsonPath('company.plan', 'pro')
            ->assertJsonPath('usage.users', 1)
            ->assertJsonCount(3, 'roles');

        $this->postJson("/api/admin/companies/{$id}/suspend")->assertOk();
        $this->assertFalse(Company::find($id)->isActive());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'bea@beta.test', 'password' => 'Secreta123!'])->assertForbidden();

        Sanctum::actingAs($this->super);
        $this->postJson("/api/admin/companies/{$id}/reactivate")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'bea@beta.test', 'password' => 'Secreta123!'])->assertOk();

        $this->assertEqualsCanonicalizing(
            ['company.created', 'company.suspended', 'company.reactivated'],
            AuditLog::where('company_id', $id)->where('action', 'like', 'company.%')->where('action', '!=', 'company.registered')->pluck('action')->all(),
        );
    }

    public function test_impersonation_round_trip(): void
    {
        Sanctum::actingAs($this->super);
        $token = $this->postJson("/api/admin/companies/{$this->acme->id}/impersonate")->assertOk()->json('token');

        $this->asToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'ana@acme.test')
            ->assertJsonPath('impersonated_by.id', $this->super->id);

        // while impersonating, data is the company's (tenant filter applies)
        $this->asToken($token)->getJson('/api/employees')->assertOk()->assertJsonCount(1, 'employees');
        // and the panel is off limits for that token
        $this->asToken($token)->getJson('/api/admin/dashboard')->assertForbidden();

        $this->asToken($token)->postJson('/api/impersonation/stop')->assertOk()->assertJsonPath('company_id', $this->acme->id);
        $this->asToken($token)->getJson('/api/user')->assertUnauthorized();

        $this->assertSame(1, AuditLog::where('action', 'company.impersonation_started')->where('actor_id', $this->super->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'company.impersonation_stopped')->where('actor_id', $this->super->id)->count());
    }

    public function test_normal_session_cannot_call_stop_impersonation(): void
    {
        Sanctum::actingAs($this->acmeAdmin);
        $this->postJson('/api/impersonation/stop')->assertStatus(422);
    }

    public function test_user_support_actions(): void
    {
        $login = $this->postJson('/api/login', ['email' => 'ana@acme.test', 'password' => 'Secreta123!'])->assertOk();
        $this->assertNotNull(User::withoutGlobalScopes()->find($this->acmeAdmin->id)->last_login_at);

        Sanctum::actingAs($this->super);
        $this->getJson('/api/admin/users?q=ana')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.company.name', 'Acme');

        $newPassword = $this->postJson("/api/admin/users/{$this->acmeAdmin->id}/reset-password")->assertOk()->json('password');
        $this->assertNotEmpty($newPassword);
        $this->asToken($login->json('token'))->getJson('/api/user')->assertUnauthorized(); // sessions revoked

        Sanctum::actingAs($this->super);
        $this->postJson("/api/admin/users/{$this->acmeAdmin->id}/disable")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'ana@acme.test', 'password' => $newPassword])->assertForbidden();

        Sanctum::actingAs($this->super);
        $this->postJson("/api/admin/users/{$this->acmeAdmin->id}/enable")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'ana@acme.test', 'password' => $newPassword])->assertOk();

        Sanctum::actingAs($this->super);
        $this->postJson("/api/admin/users/{$this->super->id}/disable")->assertStatus(422);
    }

    public function test_errors_are_recorded_grouped_and_resolvable(): void
    {
        $boom = fn () => new \RuntimeException('Algo explotó');
        ErrorLogRecorder::record($boom());
        ErrorLogRecorder::record($boom());

        $error = ErrorLog::firstOrFail();
        $this->assertSame(1, ErrorLog::count());
        $this->assertSame(2, $error->occurrences);
        $this->assertSame('RuntimeException', $error->exception);

        Sanctum::actingAs($this->super);
        $this->getJson('/api/admin/errors')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.occurrences', 2);
        $this->getJson("/api/admin/errors/{$error->id}")->assertOk()->assertJsonStructure(['trace']);
        $this->postJson("/api/admin/errors/{$error->id}/resolve")->assertOk();
        $this->getJson('/api/admin/errors')->assertJsonPath('total', 0);
        $this->getJson('/api/admin/errors?status=resolved')->assertJsonPath('total', 1);

        // the same error after being resolved opens a new entry
        ErrorLogRecorder::record($boom());
        $this->assertSame(2, ErrorLog::count());
    }

    public function test_tenant_actions_are_audited(): void
    {
        Sanctum::actingAs($this->acmeAdmin);
        $roleId = $this->postJson('/api/roles', ['name' => 'IT'])->assertCreated()->json('role.id');
        $this->putJson("/api/roles/{$roleId}/permissions", ['permission_codes' => ['documents.view']])->assertOk();

        Sanctum::actingAs($this->super);
        $actions = collect($this->getJson("/api/admin/audit?company_id={$this->acme->id}")->assertOk()->json('logs.data'))->pluck('action');
        $this->assertContains('role.created', $actions->all());
        $this->assertContains('role.permissions_changed', $actions->all());
    }
}
