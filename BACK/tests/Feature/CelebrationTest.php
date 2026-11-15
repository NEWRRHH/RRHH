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

    public function test_card_shows_one_row_per_person_with_birthday_and_anniversary(): void
    {
        $this->employee->forceFill(['birth_date' => '1990-06-20', 'hire_date' => '2023-06-15'])->save();
        $this->admin->forceFill(['birth_date' => '1985-06-17'])->save();

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/birthdays')->assertOk()->assertJsonCount(2)->json();

        // sorted by the soonest of the two dates of each person
        $this->assertSame([$this->employee->id, $this->admin->id], array_column($res, 'user_id'));
        $this->assertSame(['2026-06-15', '2026-06-17'], array_column($res, 'date'));
        $this->assertSame([true, false], array_column($res, 'is_today'));

        $this->assertSame(['date' => '2026-06-20', 'years' => 36, 'is_today' => false], $res[0]['birthday']);
        $this->assertSame(['date' => '2026-06-15', 'years' => 3, 'is_today' => true], $res[0]['anniversary']);
        $this->assertNull($res[1]['anniversary']);
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
            'birthday' => ['title' => '', 'message' => '', 'effects' => []],
            'anniversary' => ['title' => '¡Gracias {nombre}!', 'message' => '{años} junto a Acme.', 'effects' => ['fireworks', 'balloons']],
        ]];

        Sanctum::actingAs($this->employee);
        $this->putJson('/api/settings/dashboard/celebrations', $body)->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->putJson('/api/settings/dashboard/celebrations', $body)->assertOk()
            // empty fields fall back to the default texts
            ->assertJsonPath('layout.celebrations.birthday.title', '¡Feliz cumpleaños, {nombre}!')
            ->assertJsonPath('layout.celebrations.anniversary.message', '{años} junto a Acme.')
            // any combination of effects, or none (stored in catalog order)
            ->assertJsonPath('layout.celebrations.anniversary.effects', ['balloons', 'fireworks'])
            ->assertJsonPath('layout.celebrations.birthday.effects', []);

        $bad = $body;
        $bad['celebrations']['birthday']['effects'] = ['laser'];
        $this->putJson('/api/settings/dashboard/celebrations', $bad)->assertStatus(422);

        // the other settings tabs keep the texts
        $this->putJson('/api/settings/dashboard/limits', ['limits' => ['notifications' => 5, 'announcements' => 5, 'birthdays' => 5, 'holidays' => 5, 'team' => 5]])
            ->assertOk()->assertJsonPath('layout.celebrations.anniversary.title', '¡Gracias {nombre}!');

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('celebrations_today.0.title', '¡Gracias Luis!')
            ->assertJsonPath('celebrations_today.0.message', '1 año junto a Acme.')
            ->assertJsonPath('celebrations_today.0.effects', ['balloons', 'fireworks']);
    }

    public function test_feb_29_is_celebrated_on_feb_28_in_non_leap_years(): void
    {
        $next = CelebrationService::nextOccurrence(Carbon::parse('2000-02-29'), Carbon::parse('2027-01-10'));
        $this->assertSame('2027-02-28', $next->toDateString());

        $next = CelebrationService::nextOccurrence(Carbon::parse('2000-02-29'), Carbon::parse('2028-01-10'));
        $this->assertSame('2028-02-29', $next->toDateString());
    }
}
