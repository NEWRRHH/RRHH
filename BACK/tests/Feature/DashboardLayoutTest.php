<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mixed dashboard: RRHH sets the company layout, each user may rearrange
 * theirs and go back to the company one.
 */
class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;
    private User $employee;

    private const COMPANY_SLOTS = ['welcome', 'announcements', null, 'holidays', 'vacation', null, 'team', null, null];

    protected function setUp(): void
    {
        parent::setUp();
        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);

        [$this->hr, $this->employee] = Tenant::run($company->id, function () use ($company) {
            $roles = Role::pluck('id', 'name');

            return [
                User::factory()->create(['company_id' => $company->id, 'role_id' => $roles['RRHH']]),
                User::factory()->create(['company_id' => $company->id, 'role_id' => $roles['Empleado']]),
            ];
        });
    }

    protected function tearDown(): void
    {
        Tenant::clear();
        parent::tearDown();
    }

    public function test_built_in_default_until_rrhh_configures_it(): void
    {
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/dashboard/layout')->assertOk()
            ->assertJsonPath('source', 'company')
            ->assertJsonPath('slots.0', 'welcome')
            ->assertJsonPath('settings.welcome.title', 'Bienvenido')
            ->assertJsonCount(9, 'widgets');
    }

    public function test_rrhh_sets_the_company_layout_for_everybody(): void
    {
        Sanctum::actingAs($this->hr);
        $this->putJson('/api/settings/dashboard', [
            'slots' => self::COMPANY_SLOTS,
            'welcome' => ['title' => 'Hola equipo', 'subtitle' => 'Buen día', 'show_date' => false],
        ])->assertOk()->assertJsonPath('layout.configured', true);

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/dashboard/layout')->assertOk()
            ->assertJsonPath('slots', self::COMPANY_SLOTS)
            ->assertJsonPath('settings.welcome.title', 'Hola equipo')
            ->assertJsonPath('settings.welcome.show_date', false);

        $this->getJson('/api/user')->assertJsonPath('can_manage_dashboard', false);
        $this->getJson('/api/settings/dashboard')->assertForbidden();
        $this->putJson('/api/settings/dashboard', ['slots' => [], 'welcome' => []])->assertForbidden();
    }

    public function test_user_arrangement_overrides_company_until_reset(): void
    {
        Sanctum::actingAs($this->hr);
        $this->putJson('/api/settings/dashboard', ['slots' => self::COMPANY_SLOTS, 'welcome' => ['title' => 'Hola']])->assertOk();

        Sanctum::actingAs($this->employee);
        $mine = ['team', 'welcome', null, null, null, null, null, null, 'documents'];
        $this->putJson('/api/dashboard/layout', ['slots' => $mine])->assertOk()->assertJsonPath('layout.source', 'user');
        $this->getJson('/api/dashboard/layout')
            ->assertJsonPath('slots', $mine)
            ->assertJsonPath('company_slots', self::COMPANY_SLOTS)
            // the welcome message is always the company's
            ->assertJsonPath('settings.welcome.title', 'Hola');

        // other users keep the company layout
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/dashboard/layout')->assertJsonPath('source', 'company')->assertJsonPath('slots', self::COMPANY_SLOTS);

        Sanctum::actingAs($this->employee);
        $this->deleteJson('/api/dashboard/layout')->assertOk()->assertJsonPath('layout.source', 'company');
        $this->getJson('/api/dashboard/layout')->assertJsonPath('slots', self::COMPANY_SLOTS);
    }

    public function test_layout_validation(): void
    {
        Sanctum::actingAs($this->employee);
        $this->putJson('/api/dashboard/layout', ['slots' => ['welcome', 'welcome']])->assertStatus(422)->assertJsonValidationErrors('slots');
        $this->putJson('/api/dashboard/layout', ['slots' => ['hack']])->assertStatus(422)->assertJsonValidationErrors('slots');

        Sanctum::actingAs($this->hr);
        $this->putJson('/api/settings/dashboard', [
            'slots' => self::COMPANY_SLOTS,
            'welcome' => ['title' => str_repeat('x', 81)],
        ])->assertStatus(422)->assertJsonValidationErrors('welcome.title');
    }

    public function test_card_limits_default_to_ten_and_rrhh_can_change_them(): void
    {
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/dashboard/layout')->assertOk()
            ->assertJsonPath('settings.limits.notifications', 10)
            ->assertJsonPath('settings.limits.announcements', 10);

        $limits = ['notifications' => 5, 'announcements' => 3, 'birthdays' => 10, 'holidays' => 50, 'team' => 1];
        $this->putJson('/api/settings/dashboard/limits', ['limits' => $limits])->assertForbidden();

        Sanctum::actingAs($this->hr);
        $this->putJson('/api/settings/dashboard', ['slots' => self::COMPANY_SLOTS, 'welcome' => ['title' => 'Hola']])->assertOk();
        $this->putJson('/api/settings/dashboard/limits', ['limits' => $limits])->assertOk()->assertJsonPath('layout.limits', $limits);

        // saving limits keeps the layout, and saving the layout keeps the limits
        $this->putJson('/api/settings/dashboard', ['slots' => self::COMPANY_SLOTS, 'welcome' => ['title' => 'Hola 2']])->assertOk();

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/dashboard/layout')
            ->assertJsonPath('slots', self::COMPANY_SLOTS)
            ->assertJsonPath('settings.welcome.title', 'Hola 2')
            ->assertJsonPath('settings.limits', $limits);
    }

    public function test_card_limits_validation(): void
    {
        Sanctum::actingAs($this->hr);
        $base = ['notifications' => 10, 'announcements' => 10, 'birthdays' => 10, 'holidays' => 10, 'team' => 10];

        $this->putJson('/api/settings/dashboard/limits', ['limits' => ['notifications' => 0] + $base])
            ->assertStatus(422)->assertJsonValidationErrors('limits.notifications');
        $this->putJson('/api/settings/dashboard/limits', ['limits' => ['team' => 51] + $base])
            ->assertStatus(422)->assertJsonValidationErrors('limits.team');
        $this->putJson('/api/settings/dashboard/limits', ['limits' => ['notifications' => 10]])
            ->assertStatus(422)->assertJsonValidationErrors('limits.announcements');
    }

    public function test_company_layouts_are_isolated(): void
    {
        Sanctum::actingAs($this->hr);
        $this->putJson('/api/settings/dashboard', ['slots' => self::COMPANY_SLOTS, 'welcome' => ['title' => 'Solo Acme']])->assertOk();

        ['admin' => $other] = app(CompanyProvisioner::class)
            ->provision('Beta', ['name' => 'Bea', 'email' => 'bea@beta.test', 'password' => 'Secreta123!']);
        Sanctum::actingAs($other);
        $this->getJson('/api/dashboard/layout')->assertJsonPath('settings.welcome.title', 'Bienvenido')->assertJsonPath('slots.1', 'birthdays');
        $this->getJson('/api/settings/dashboard')->assertOk()->assertJsonPath('configured', false);
    }
}
