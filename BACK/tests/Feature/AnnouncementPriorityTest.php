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
 * Announcements carry a priority (normal / importante / urgente); everybody
 * reads them, only announcements.manage publishes.
 */
class AnnouncementPriorityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        ['company' => $company, 'admin' => $this->admin] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);

        $this->employee = Tenant::run($company->id, fn () => User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', 'Empleado')->value('id'),
        ]));
    }

    protected function tearDown(): void
    {
        Tenant::clear();
        parent::tearDown();
    }

    public function test_priority_is_stored_and_listed(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/announcements', ['title' => 'Corte de luz', 'body' => 'Mañana', 'scope' => 'all', 'priority' => 'urgente'])->assertOk();
        $this->postJson('/api/announcements', ['title' => 'Asado', 'body' => 'Viernes', 'scope' => 'all'])->assertOk();
        $this->postJson('/api/announcements', ['title' => 'X', 'body' => 'Y', 'scope' => 'all', 'priority' => 'altísima'])->assertStatus(422);

        // an employee without the permission reads them but can't publish
        Sanctum::actingAs($this->employee);
        $rows = collect($this->getJson('/api/announcements')->assertOk()->json())->keyBy('title');
        $this->assertSame('urgente', $rows['Corte de luz']['priority']);
        $this->assertSame('normal', $rows['Asado']['priority']);

        $this->postJson('/api/announcements', ['title' => 'Hola', 'body' => 'Chau', 'scope' => 'all'])->assertForbidden();
    }
}
