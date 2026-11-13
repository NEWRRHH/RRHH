<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Holiday;
use App\Models\NationalHoliday;
use App\Models\Plan;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HolidaysAndPlansTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        ['company' => $this->company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
    }

    protected function tearDown(): void
    {
        Tenant::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('festivos.csv', $content);
    }

    public function test_crud_and_permission(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/holidays', ['date' => '2030-12-25', 'name' => 'Navidad', 'recurring' => true])->assertCreated()->json('holiday.id');
        $this->postJson('/api/holidays', ['date' => '2030-12-25', 'name' => 'Otra'])->assertStatus(422)->assertJsonValidationErrors('date');
        $this->putJson("/api/holidays/{$id}", ['date' => '2030-12-25', 'name' => 'Navidad!'])->assertOk();

        // recurring: shown on every year
        $this->getJson('/api/holidays?year=2031')->assertOk()->assertJsonPath('holidays.0.date', '2031-12-25')->assertJsonPath('holidays.0.name', 'Navidad!');

        // an employee cannot manage holidays but sees the upcoming ones
        $employee = Tenant::run($this->company->id, fn () => User::factory()->create(['company_id' => $this->company->id]));
        Sanctum::actingAs($employee);
        $this->getJson('/api/holidays')->assertForbidden();
        $this->postJson('/api/holidays', ['date' => '2030-01-01', 'name' => 'x'])->assertForbidden();
        $this->getJson('/api/holidays/upcoming')->assertOk();

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/holidays/{$id}")->assertOk();
        $this->assertSame(0, Holiday::withoutGlobalScopes()->count());
    }

    public function test_csv_preview_and_commit(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/holidays', ['date' => '2030-01-06', 'name' => 'Reyes'])->assertCreated();

        $content = "\xEF\xBB\xBFfecha;nombre;tipo;recurrente\n"
            . "01/01/2030;Año Nuevo;nacional;si\n"
            . "2030-01-06;Reyes Magos;nacional;no\n"
            . "31/02/2030;Fecha mala;nacional;no\n"
            . "15/05/2030;;regional;no\n"
            . "24/12/2030;Cierre;empresa;no\n";

        $preview = $this->post('/api/holidays/import', ['file' => $this->csv($content), 'mode' => 'preview'], ['Accept' => 'application/json'])
            ->assertOk()->json();
        $this->assertSame(['total' => 5, 'new' => 2, 'duplicate' => 1, 'error' => 2], $preview['summary']);
        $this->assertSame('national', $preview['rows'][0]['type']);
        $this->assertTrue($preview['rows'][0]['recurring']);
        $this->assertSame('company', $preview['rows'][4]['type']);

        // preview writes nothing
        $this->assertSame(1, Holiday::withoutGlobalScopes()->count());

        $this->post('/api/holidays/import', ['file' => $this->csv($content), 'mode' => 'commit', 'on_duplicate' => 'overwrite'], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['created' => 2, 'updated' => 1]);
        $this->assertSame('Reyes Magos', Holiday::withoutGlobalScopes()->whereDate('date', '2030-01-06')->value('name'));
        $this->assertSame(3, Holiday::withoutGlobalScopes()->where('company_id', $this->company->id)->count());

        // comma separated, no header
        $this->post('/api/holidays/import', ['file' => $this->csv("01/05/2030,Trabajo\n"), 'mode' => 'commit'], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['created' => 1]);
    }

    public function test_import_from_national_catalog(): void
    {
        NationalHoliday::create(['country_code' => 'ES', 'date' => '2020-12-25', 'name' => 'Navidad', 'recurring' => true]);
        NationalHoliday::create(['country_code' => 'ES', 'date' => '2030-04-18', 'name' => 'Viernes Santo']);
        NationalHoliday::create(['country_code' => 'AR', 'date' => '2030-05-25', 'name' => 'Revolución de Mayo']);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/holidays/import-national', ['country_code' => 'es', 'year' => 2030])->assertOk()->assertJson(['created' => 2]);
        $this->postJson('/api/holidays/import-national', ['country_code' => 'ES', 'year' => 2030])->assertOk()->assertJson(['created' => 0]);

        $dates = Holiday::withoutGlobalScopes()->orderBy('date')->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $this->assertSame(['2030-04-18', '2030-12-25'], $dates);
    }

    public function test_holidays_do_not_consume_vacation_days(): void
    {
        Carbon::setTestNow('2030-01-01 10:00:00');
        $this->admin->forceFill(['vacation_days_total' => 30])->save();

        Sanctum::actingAs($this->admin);
        // Monday 2030-01-07 .. Friday 2030-01-11 = 5 working days; Wednesday is a holiday
        $this->postJson('/api/holidays', ['date' => '2030-01-09', 'name' => 'Fiesta local'])->assertCreated();
        $types = $this->getJson('/api/timeoff/calendar?year=2030')->json('event_types');
        $vacation = collect($types)->firstWhere('name', 'Vacaciones')['id'];

        $this->postJson('/api/timeoff/events', [
            'title' => 'Vacaciones', 'event_type_id' => $vacation,
            'start_date' => '2030-01-07', 'end_date' => '2030-01-11', 'all_day' => true,
        ])->assertOk()->assertJsonPath('working_days', 4);

        $this->getJson('/api/vacations/me')->assertOk()->assertJsonPath('vacation_days_requested_year', 4);
        $this->getJson('/api/holidays/upcoming')->assertOk()->assertJsonPath('0.name', 'Fiesta local');
    }

    public function test_holidays_are_per_company(): void
    {
        ['admin' => $other] = app(CompanyProvisioner::class)
            ->provision('Beta', ['name' => 'Bea', 'email' => 'bea@beta.test', 'password' => 'Secreta123!']);

        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/holidays', ['date' => '2030-03-19', 'name' => 'San José'])->assertCreated()->json('holiday.id');

        Sanctum::actingAs($other);
        $this->getJson('/api/holidays?year=2030')->assertOk()->assertJsonCount(0, 'holidays');
        $this->deleteJson("/api/holidays/{$id}")->assertNotFound();
        // same date is free in the other company
        $this->postJson('/api/holidays', ['date' => '2030-03-19', 'name' => 'Otro'])->assertCreated();
    }

    public function test_plan_employee_limit(): void
    {
        Plan::where('code', 'free')->update(['max_employees' => 2]);
        Sanctum::actingAs($this->admin);

        $payload = fn ($n) => ['name' => "U{$n}", 'email' => "u{$n}@acme.test", 'password' => 'Secreta123!', 'password_confirmation' => 'Secreta123!'];
        $this->postJson('/api/employees', $payload(1))->assertCreated();
        $this->postJson('/api/employees', $payload(2))->assertStatus(422)->assertJsonFragment(['message' => 'Has alcanzado el límite de 2 empleados de tu plan. Contacta con el soporte para ampliarlo.']);

        $this->getJson('/api/user')->assertJsonPath('plan_usage.employees.used', 2)->assertJsonPath('plan_usage.employees.max', 2);
    }

    public function test_plan_storage_limit(): void
    {
        Plan::where('code', 'free')->update(['max_storage_mb' => 1]);
        Sanctum::actingAs($this->admin);

        $this->post('/api/documents/upload', [
            'file' => UploadedFile::fake()->create('grande.pdf', 2048, 'application/pdf'),
            'category' => 'payroll',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonFragment(['message' => 'No hay espacio suficiente: tu plan incluye 1 MB de almacenamiento.']);
    }

    public function test_super_admin_config(): void
    {
        $super = User::factory()->create(['company_id' => null]);
        $super->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($super);

        $this->getJson('/api/admin/plans')->assertOk()->assertJsonCount(3)->assertJsonPath('0.companies_count', 1);
        $planId = $this->postJson('/api/admin/plans', ['code' => 'enterprise', 'name' => 'Enterprise', 'max_employees' => 500])->assertCreated()->json('plan.id');
        $this->putJson("/api/admin/companies/{$this->company->id}", ['name' => 'Acme', 'plan' => 'enterprise', 'country_code' => 'ar'])->assertOk();
        $this->assertSame('AR', $this->company->fresh()->country_code);
        $this->deleteJson("/api/admin/plans/{$planId}")->assertStatus(422); // in use
        $this->getJson("/api/admin/companies/{$this->company->id}")->assertJsonPath('plan_usage.plan.code', 'enterprise');

        $this->postJson('/api/admin/national-holidays', ['country_code' => 'es', 'date' => '2030-10-12', 'name' => 'Fiesta Nacional'])->assertCreated();
        $this->post('/api/admin/national-holidays/import', [
            'country_code' => 'ES', 'mode' => 'commit',
            'file' => $this->csv("fecha;nombre;tipo\n01/11/2030;Todos los Santos;nacional\n10/12/2030;Mal tipo;empresa\n"),
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['created' => 1]);
        $this->getJson('/api/admin/national-holidays?country_code=ES&year=2030')->assertOk()->assertJsonCount(2, 'holidays');

        $this->getJson('/api/admin/permissions')->assertOk()->assertJsonFragment(['code' => 'holidays.manage']);

        // not for company users
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/plans')->assertForbidden();
    }
}
