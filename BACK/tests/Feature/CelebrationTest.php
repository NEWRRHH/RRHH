<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\CelebrationService;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dashboard card lists birthdays and work anniversaries; /api/user tells the
 * logged-in user whether today is one of theirs (celebration animation).
 */
class CelebrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 10:00:00');

        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);

        $this->employee = Tenant::run($company->id, fn () => User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', 'Empleado')->value('id'),
        ]));

        $this->admin->forceFill(['birth_date' => null, 'hire_date' => null])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::clear();
        parent::tearDown();
    }

    public function test_card_mixes_birthdays_and_anniversaries_sorted_by_date(): void
    {
        $this->employee->forceFill(['birth_date' => '1990-06-20', 'hire_date' => '2023-06-15'])->save();

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/birthdays')->assertOk()->assertJsonCount(2)->json();

        $this->assertSame(['anniversary', 'birthday'], array_column($res, 'type'));
        $this->assertSame(['2026-06-15', '2026-06-20'], array_column($res, 'date'));
        $this->assertSame([3, 36], array_column($res, 'years'));
        $this->assertSame([true, false], array_column($res, 'is_today'));
    }

    public function test_hire_day_itself_and_future_hires_are_not_anniversaries(): void
    {
        $this->employee->forceFill(['birth_date' => null, 'hire_date' => '2026-06-15'])->save();
        $this->admin->forceFill(['hire_date' => '2026-09-01'])->save();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/birthdays')->assertOk()->assertJsonCount(0);
    }

    public function test_user_payload_reports_todays_celebrations(): void
    {
        $this->employee->forceFill(['first_name' => 'Luis', 'birth_date' => '1990-06-15', 'hire_date' => '2020-06-15'])->save();

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/user')->assertOk()
            ->assertJsonCount(2, 'celebrations_today')
            ->assertJsonPath('celebrations_today.0.type', 'birthday')
            ->assertJsonPath('celebrations_today.0.years', 36)
            ->assertJsonPath('celebrations_today.0.title', '¡Feliz cumpleaños, Luis!')
            ->assertJsonPath('celebrations_today.1.type', 'anniversary')
            ->assertJsonPath('celebrations_today.1.years', 6)
            ->assertJsonPath('celebrations_today.1.message', 'Hoy cumples 6 años con nosotros. ¡Gracias por ser parte del equipo!');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('celebrations_today', []);
    }

    public function test_rrhh_customises_the_anniversary_message(): void
    {
        $this->employee->forceFill(['first_name' => 'Luis', 'birth_date' => null, 'hire_date' => '2025-06-15'])->save();
        $body = ['celebrations' => [
            'birthday' => ['title' => '', 'message' => ''],
            'anniversary' => ['title' => '¡Gracias {nombre}!', 'message' => '{años} junto a Acme.'],
        ]];

        Sanctum::actingAs($this->employee);
        $this->putJson('/api/settings/dashboard/celebrations', $body)->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->putJson('/api/settings/dashboard/celebrations', $body)->assertOk()
            // empty fields fall back to the default texts
            ->assertJsonPath('layout.celebrations.birthday.title', '¡Feliz cumpleaños, {nombre}!')
            ->assertJsonPath('layout.celebrations.anniversary.message', '{años} junto a Acme.');

        // the other settings tabs keep the texts
        $this->putJson('/api/settings/dashboard/limits', ['limits' => ['notifications' => 5, 'announcements' => 5, 'birthdays' => 5, 'holidays' => 5, 'team' => 5]])
            ->assertOk()->assertJsonPath('layout.celebrations.anniversary.title', '¡Gracias {nombre}!');

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('celebrations_today.0.title', '¡Gracias Luis!')
            ->assertJsonPath('celebrations_today.0.message', '1 año junto a Acme.');
    }

    public function test_feb_29_is_celebrated_on_feb_28_in_non_leap_years(): void
    {
        $next = CelebrationService::nextOccurrence(Carbon::parse('2000-02-29'), Carbon::parse('2027-01-10'));
        $this->assertSame('2027-02-28', $next->toDateString());

        $next = CelebrationService::nextOccurrence(Carbon::parse('2000-02-29'), Carbon::parse('2028-01-10'));
        $this->assertSame('2028-02-29', $next->toDateString());
    }
}
