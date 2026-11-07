<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EventType;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public sign-up creates a whole company with its first user as Admin.
 */
class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme S.A.',
            'name' => 'Ana Pérez',
            'email' => 'ana@acme.test',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
        ], $overrides);
    }

    public function test_sign_up_creates_company_with_admin_and_defaults(): void
    {
        $response = $this->postJson('/api/register', $this->payload())->assertCreated();

        $company = Company::where('slug', 'acme-sa')->firstOrFail();
        $this->assertSame('Acme S.A.', $company->name);
        $this->assertTrue($company->isActive());

        $user = User::withoutGlobalScopes()->where('email', 'ana@acme.test')->firstOrFail();
        $this->assertSame($company->id, $user->company_id);
        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
        $this->assertSame('Ana', $user->first_name);
        $this->assertSame('Pérez', $user->last_name);

        $roles = Role::withoutGlobalScopes()->where('company_id', $company->id)->pluck('name')->sort()->values();
        $this->assertSame(['Admin', 'Empleado', 'RRHH'], $roles->all());

        $codes = EventType::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('code')->pluck('code')->sort()->values();
        $this->assertSame([EventType::ATTENDANCE_REQUEST, EventType::VACATION], $codes->all());

        $this->assertSame(1, Schedule::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $response->assertJsonPath('user.company.slug', 'acme-sa')
            ->assertJsonPath('user.role.is_admin', true)
            ->assertJsonStructure(['token']);
    }

    public function test_new_admin_can_use_the_app_right_away(): void
    {
        $token = $this->postJson('/api/register', $this->payload())->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJson(['is_admin' => true, 'is_super_admin' => false, 'can_access_settings' => true]);

        $this->withToken($token)->getJson('/api/employees')->assertOk()->assertJsonCount(1, 'employees');
        $this->withToken($token)->postJson('/api/employees', [
            'name' => 'Juan', 'email' => 'juan@acme.test', 'password' => 'Secreta123!', 'password_confirmation' => 'Secreta123!',
        ])->assertCreated();
    }

    public function test_two_companies_with_the_same_name_are_separate(): void
    {
        $this->postJson('/api/register', $this->payload())->assertCreated();
        $second = $this->postJson('/api/register', $this->payload(['email' => 'otro@acme.test']))->assertCreated();

        $this->assertSame('acme-sa-2', $second->json('user.company.slug'));
        $this->assertSame(2, Company::count());

        // the second admin only sees their own company
        $this->withToken($second->json('token'))->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.email', 'otro@acme.test');
    }

    public function test_validation(): void
    {
        $this->postJson('/api/register', $this->payload(['company_name' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('company_name');
        $this->postJson('/api/register', $this->payload(['password' => 'debil', 'password_confirmation' => 'debil']))
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/register', $this->payload())->assertCreated();
        $this->postJson('/api/register', $this->payload(['company_name' => 'Otra']))
            ->assertStatus(422)->assertJsonValidationErrors('email');

        // nothing half-created by the failed attempts
        $this->assertSame(1, Company::count());
    }
}
