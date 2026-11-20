<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * - Company logo on signup / from Configuración (sidebar + favicon).
 * - Clock-ins left open are closed at the end of the schedule with a note.
 */
class CompanyLogoAndAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::clear();
        parent::tearDown();
    }

    private function pngDataUrl(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    private function signup(array $extra = [])
    {
        return $this->postJson('/api/register', [
            'company_name' => 'Acme', 'name' => 'Ana', 'email' => 'ana@acme.test',
            'password' => 'Secreta123!', 'password_confirmation' => 'Secreta123!',
        ] + $extra);
    }

    public function test_signup_with_logo_and_admin_can_change_it(): void
    {
        $this->signup(['logo' => 'data:image/svg+xml;base64,' . base64_encode('<svg/>')])->assertStatus(422);
        $this->assertSame(0, Company::count());

        $this->signup(['logo' => $this->pngDataUrl()])->assertCreated();
        $company = Company::firstOrFail();
        $this->assertNotNull($company->logo);
        Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $company->logo), '/'));

        $admin = User::where('email', 'ana@acme.test')->firstOrFail();
        Sanctum::actingAs($admin);
        $this->getJson('/api/user')->assertJsonPath('company.logo', $company->logo);

        $old = $company->logo;
        $new = $this->post('/api/settings/company-logo', ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)], ['Accept' => 'application/json'])
            ->assertOk()->json('logo');
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing(ltrim(str_replace('/storage/', '', $old), '/'));

        $this->deleteJson('/api/settings/company-logo')->assertOk();
        $this->assertNull($company->fresh()->logo);

        // employees can't change it
        $employee = Tenant::run($company->id, fn () => User::factory()->create([
            'company_id' => $company->id, 'role_id' => Role::where('name', 'Empleado')->value('id'),
        ]));
        Sanctum::actingAs($employee);
        $this->deleteJson('/api/settings/company-logo')->assertForbidden();
    }

    public function test_forgotten_clock_out_is_closed_at_end_of_schedule_with_note(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00'); // Wednesday
        ['company' => $company, 'admin' => $admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);

        // yesterday: clocked in at 09:10 and never out; today: still working
        Tenant::run($company->id, function () use ($admin) {
            Attendance::table()->insert([
                ['user_id' => $admin->id, 'date' => '2026-11-17', 'start_time' => '09:10:00', 'status' => 'en_trabajo', 'created_at' => now(), 'updated_at' => now()],
                ['user_id' => $admin->id, 'date' => '2026-11-18', 'start_time' => '09:00:00', 'status' => 'en_trabajo', 'created_at' => now(), 'updated_at' => now()],
            ]);
        });

        $this->artisan('attendance:auto-close')->assertSuccessful();

        $rows = Tenant::run($company->id, fn () => Attendance::table()->orderBy('date')->get());
        $schedule = app(\App\Services\ScheduleService::class)->loadEffectiveScheduleForUser($admin->id);
        $expectedEnd = substr((string) $schedule->end_time, 0, 5);

        $this->assertSame('fuera_trabajo', $rows[0]->status);
        $this->assertSame($expectedEnd, substr((string) $rows[0]->end_time, 0, 5));
        $this->assertNotNull($rows[0]->auto_closed_at);
        $this->assertStringContainsString('Salida no fichada', (string) $rows[0]->note);
        $this->assertSame('en_trabajo', $rows[1]->status); // today is left alone

        // the employee sees the notice until they dismiss it; Reportes flags the day
        Sanctum::actingAs($admin);
        $this->getJson('/api/attendance/auto-closed')->assertOk()
            ->assertJsonCount(1, 'records')->assertJsonPath('records.0.end_time', $expectedEnd);
        $row = collect($this->getJson('/api/attendance/month?month=2026-11')->json('rows'))->firstWhere('date', '2026-11-17');
        $this->assertTrue($row['auto_closed']);

        $this->postJson('/api/attendance/auto-closed/seen')->assertOk();
        $this->getJson('/api/attendance/auto-closed')->assertJsonCount(0, 'records');
    }
}
