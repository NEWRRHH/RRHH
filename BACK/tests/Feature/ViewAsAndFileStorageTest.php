<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Ver como" (read-only, audited, no private chat) and per-company file
 * storage on the disk configured in .env (FILES_DISK / PUBLIC_FILES_DISK).
 */
class ViewAsAndFileStorageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;
    private User $employee;
    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);
        $this->companyId = $company->id;

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

    /** Real bearer tokens (not Sanctum::actingAs) so the token name matters. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_view_as_is_read_only_audited_and_hides_the_chat(): void
    {
        $hrToken = $this->hr->createToken('test')->plainTextToken;

        // RRHH can't look as the admin; employees can't use it at all
        $this->as($hrToken)->postJson("/api/employees/{$this->admin->id}/view-as")->assertForbidden();
        $employeeToken = $this->employee->createToken('test')->plainTextToken;
        $this->as($employeeToken)->postJson("/api/employees/{$this->hr->id}/view-as")->assertForbidden();

        $res = $this->as($hrToken)->postJson("/api/employees/{$this->employee->id}/view-as")->assertOk();
        $viewToken = $res->json('token');

        // the app sees the employee, flagged as a view-as session
        $this->as($viewToken)->getJson('/api/user')->assertOk()
            ->assertJsonPath('id', $this->employee->id)
            ->assertJsonPath('viewed_by.id', $this->hr->id);
        $this->as($viewToken)->getJson('/api/dashboard')->assertOk();

        // read-only
        $this->as($viewToken)->postJson('/api/attendance/start')
            ->assertForbidden()->assertJsonPath('reason', 'view_as_read_only');
        // private chat stays closed, even for reading
        $this->as($viewToken)->getJson('/api/notifications/conversations')
            ->assertForbidden()->assertJsonPath('reason', 'view_as_private');
        // and no realtime channels
        $this->as($viewToken)->postJson('/broadcasting/auth', ['channel_name' => "private-user.{$this->employee->id}"])
            ->assertForbidden();

        // leaving revokes the token; everything is in the audit log
        $this->as($viewToken)->postJson('/api/view-as/stop')->assertOk();
        $this->as($viewToken)->getJson('/api/user')->assertUnauthorized();

        $actions = AuditLog::withoutGlobalScopes()->pluck('action')->all();
        $this->assertContains('employee.view_as_started', $actions);
        $this->assertContains('employee.view_as_stopped', $actions);
    }

    public function test_payslips_dropped_in_the_employee_folder_show_in_documents(): void
    {
        Storage::fake('local');
        $this->employee->forceFill(['first_name' => 'Juan', 'last_name' => 'Pérez', 'dni' => '30111222'])->save();
        $root = "companies/acme/nominas";
        // by name (accents / case / separators don't matter) and by DNI
        Storage::disk('local')->put("{$root}/JUAN_PEREZ/nomina-2026-05.pdf", 'pdf');
        Storage::disk('local')->put("{$root}/30111222/nomina-2026-06.pdf", 'pdf');
        Storage::disk('local')->put("{$root}/Nadie Conocido/nomina.pdf", 'pdf');

        $token = $this->employee->createToken('test')->plainTextToken;
        $docs = $this->as($token)->getJson('/api/documents?category=payroll')->assertOk()->json('documents');
        $this->assertEqualsCanonicalizing(['nomina-2026-05.pdf', 'nomina-2026-06.pdf'], array_column($docs, 'original_name'));

        // not registered twice; the employee can download them
        $this->as($token)->getJson('/api/documents?category=payroll')->assertJsonCount(2, 'documents');
        $this->as($token)->get("/api/documents/{$docs[0]['id']}/download")->assertOk();

        // the command reports folders that match nobody
        $this->artisan('nominas:sync', ['--company' => $this->companyId])
            ->expectsOutputToContain('sin empleado')
            ->assertSuccessful();
    }

    public function test_folders_are_created_by_company_name_with_one_payroll_folder_per_employee(): void
    {
        // two people with the same name: the DNI in the folder tells them apart
        $this->employee->forceFill(['first_name' => 'Juan', 'last_name' => 'Pérez', 'dni' => '30111222'])->save();
        $this->hr->forceFill(['first_name' => 'Juan', 'last_name' => 'Pérez', 'dni' => '40999888'])->save();

        $this->artisan('files:folders')->assertSuccessful();

        $disk = Storage::disk('local');
        foreach (['documents', 'chat', 'nominas'] as $area) {
            $this->assertTrue($disk->directoryExists("companies/acme/{$area}"));
        }
        $this->assertTrue(Storage::disk('public')->directoryExists('companies/acme/avatars'));
        $this->assertTrue($disk->directoryExists('companies/acme/nominas/Juan Pérez - 30111222'));
        $this->assertTrue($disk->directoryExists('companies/acme/nominas/Juan Pérez - 40999888'));

        // running it again doesn't duplicate folders
        $this->artisan('files:folders')->assertSuccessful();
        $this->assertCount(3, $disk->directories('companies/acme/nominas')); // 2 Juan + the admin

        // a payslip in each folder reaches the right person
        $disk->put('companies/acme/nominas/Juan Pérez - 30111222/mayo.pdf', 'pdf');
        $disk->put('companies/acme/nominas/Juan Pérez - 40999888/junio.pdf', 'pdf');
        $this->artisan('nominas:sync')->assertSuccessful();

        $names = fn (User $u) => Document::withoutGlobalScopes()->where('user_id', $u->id)->pluck('original_name')->all();
        $this->assertSame(['mayo.pdf'], $names($this->employee));
        $this->assertSame(['junio.pdf'], $names($this->hr));
    }

    public function test_new_payslip_notifies_the_employee_until_seen(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\DocumentsUpdated::class]);
        $this->employee->forceFill(['first_name' => 'Juan', 'last_name' => 'Pérez', 'dni' => '30111222'])->save();
        Storage::disk('local')->put('companies/acme/nominas/Juan Pérez - 30111222/mayo.pdf', 'pdf');

        // the scheduler registers it and pushes the badge in real time
        $this->artisan('nominas:sync')->assertSuccessful();
        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\DocumentsUpdated::class,
            fn ($e) => $e->userId === $this->employee->id && $e->unread === 1 && $e->created === 1,
        );

        $token = $this->employee->createToken('test')->plainTextToken;
        $this->as($token)->getJson('/api/documents/unread')->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('by_category.payroll', 1);
        $this->as($token)->getJson('/api/documents/summary')->assertJsonPath('unread', 1);
        $this->as($token)->getJson('/api/documents?category=payroll')->assertJsonPath('documents.0.read_at', null);

        // opening Nóminas clears it (and tells the other tabs)
        $this->as($token)->postJson('/api/documents/read', ['category' => 'payroll'])->assertOk()->assertJsonPath('unread', 0);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\DocumentsUpdated::class, fn ($e) => $e->unread === 0);
        $this->as($token)->getJson('/api/documents/unread')->assertJsonPath('unread', 0);

        // own uploads never show as new
        $this->as($token)->post('/api/documents/upload', [
            'file' => UploadedFile::fake()->create('justificante.pdf', 20, 'application/pdf'),
            'category' => 'medical',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->as($token)->getJson('/api/documents/unread')->assertJsonPath('unread', 0);
    }

    public function test_new_company_gets_the_national_holidays_template(): void
    {
        \App\Models\NationalHoliday::withoutGlobalScopes()->create(['country_code' => 'ES', 'date' => '2020-12-25', 'name' => 'Navidad', 'recurring' => true]);

        ['company' => $other] = app(CompanyProvisioner::class)
            ->provision('Nano Tech', ['name' => 'Bea', 'email' => 'bea@nano.test', 'password' => 'Secreta123!']);

        $this->assertSame('nano-tech', $other->slug);
        $this->assertSame(1, \App\Models\NationalHoliday::withoutGlobalScopes()->where('company_id', $other->id)->count());
        $this->assertTrue(Storage::disk('local')->directoryExists('companies/nano-tech/nominas'));
    }

    public function test_new_employee_gets_a_payroll_folder(): void
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        $this->as($token)->postJson('/api/employees', [
            'name' => 'Lucía Gómez', 'first_name' => 'Lucía', 'last_name' => 'Gómez', 'dni' => '25555666',
            'email' => 'lucia@acme.test', 'password' => 'Secreta123!', 'password_confirmation' => 'Secreta123!',
        ])->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->directoryExists('companies/acme/nominas/Lucía Gómez - 25555666'));
    }

    public function test_files_are_stored_per_company_on_the_configured_disk(): void
    {
        config(['filesystems.files_disk' => 'files_test']);
        config(['filesystems.disks.files_test' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/files_test')]]);
        Storage::fake('files_test');

        $token = $this->admin->createToken('test')->plainTextToken;
        $this->as($token)->post('/api/documents/upload', [
            'file' => UploadedFile::fake()->create('nomina.pdf', 50, 'application/pdf'),
            'category' => 'receipt',
        ], ['Accept' => 'application/json'])->assertOk();

        $path = Document::withoutGlobalScopes()->value('filename');
        $this->assertStringStartsWith("companies/acme/documents/{$this->admin->id}/", $path);
        Storage::disk('files_test')->assertExists($path);

        // and it is served from that same disk
        $id = Document::withoutGlobalScopes()->value('id');
        $this->as($token)->get("/api/documents/{$id}/download")->assertOk();
    }
}
