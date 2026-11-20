<?php

namespace Tests\Feature;

use App\Events\DocumentsUpdated;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nóminas module: RRHH uploads the gestoría's payslips (named with the DNI),
 * fixes the ones that didn't match and sends them to each employee.
 */
class PayrollModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $juan;
    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([DocumentsUpdated::class]);

        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Bea', 'email' => 'bea@acme.test', 'password' => 'Secreta123!']);

        [$this->juan, $this->ana] = Tenant::run($company->id, function () use ($company) {
            $role = Role::where('name', 'Empleado')->value('id');

            return [
                User::factory()->create(['company_id' => $company->id, 'role_id' => $role, 'first_name' => 'Juan', 'dni' => '30.111.222-A']),
                User::factory()->create(['company_id' => $company->id, 'role_id' => $role, 'first_name' => 'Ana', 'dni' => '40999888B']),
            ];
        });
    }

    protected function tearDown(): void
    {
        Tenant::clear();
        parent::tearDown();
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 30, 'application/pdf');
    }

    public function test_upload_match_by_dni_fix_and_send(): void
    {
        Sanctum::actingAs($this->admin);
        $batchId = $this->postJson('/api/payroll/batches', ['period' => '2026-05'])->assertOk()->json('batch.id');

        $files = $this->post("/api/payroll/batches/{$batchId}/files", ['files' => [
            $this->pdf('30111222A.pdf'),              // DNI with letter
            $this->pdf('nomina_40999888_2026-05.pdf'), // DNI without letter, inside a longer name
            $this->pdf('desconocido.pdf'),            // no DNI
        ]], ['Accept' => 'application/json'])->assertOk()->json('files');

        $byName = collect($files)->keyBy('original_name');
        $this->assertSame($this->juan->id, $byName['30111222A.pdf']['user']['id']);
        $this->assertSame($this->ana->id, $byName['nomina_40999888_2026-05.pdf']['user']['id']);
        $this->assertNull($byName['desconocido.pdf']['user']);

        // RRHH assigns the unmatched one... then thinks better and removes it
        $unknown = $byName['desconocido.pdf']['id'];
        $this->putJson("/api/payroll/batches/{$batchId}/files/{$unknown}", ['user_id' => $this->ana->id])
            ->assertOk()->assertJsonPath('file.match', 'manual');
        $this->deleteJson("/api/payroll/batches/{$batchId}/files/{$unknown}")->assertOk();

        $this->postJson("/api/payroll/batches/{$batchId}/send")->assertOk()
            ->assertJsonPath('sent', 2)
            ->assertJsonPath('batch.status', 'sent');

        // each payslip is in the employee's DNI folder and in their Documentos
        $doc = Document::withoutGlobalScopes()->where('user_id', $this->juan->id)->first();
        $this->assertSame('companies/acme/nominas/30111222A/nomina-2026-05.pdf', $doc->filename);
        $this->assertSame('payroll', $doc->category);
        $this->assertSame('Nómina Mayo 2026', $doc->description);
        $this->assertNull($doc->read_at);
        Storage::disk('local')->assertExists($doc->filename);
        Event::assertDispatched(DocumentsUpdated::class, fn ($e) => $e->userId === $this->juan->id);

        Sanctum::actingAs($this->juan);
        $this->getJson('/api/documents?category=payroll')->assertJsonCount(1, 'documents');

        // a sent batch can't be changed or deleted
        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/payroll/batches/{$batchId}")->assertStatus(422);
    }

    public function test_only_payroll_managers(): void
    {
        Sanctum::actingAs($this->juan);
        $this->getJson('/api/payroll/batches')->assertForbidden();
        $this->postJson('/api/payroll/batches', ['period' => '2026-05'])->assertForbidden();
    }

    public function test_dni_and_social_security_are_required_and_dni_unique(): void
    {
        Sanctum::actingAs($this->admin);
        $base = ['name' => 'Luis', 'email' => 'luis@acme.test', 'password' => 'Secreta123!', 'password_confirmation' => 'Secreta123!'];

        $this->postJson('/api/employees', $base)->assertStatus(422)->assertJsonValidationErrors(['dni', 'social_security_number']);
        $this->postJson('/api/employees', $base + ['dni' => '40999888B', 'social_security_number' => '281234567890'])
            ->assertStatus(422)->assertJsonValidationErrors('dni');
        $this->postJson('/api/employees', $base + ['dni' => '50555666C', 'social_security_number' => '281234567890'])->assertCreated();
    }
}
