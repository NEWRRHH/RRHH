<?php

namespace Tests\Feature;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\ChatAttachment;
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
 * Chat messages can carry a file or an image; only the two participants
 * can download it and it counts towards the plan storage.
 */
class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $luis;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // only the broadcasts (Eloquent events must keep working)
        Event::fake([MessageSent::class, MessageRead::class]);

        ['company' => $company, 'admin' => $this->ana] = app(CompanyProvisioner::class)
            ->provision('Acme', ['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'Secreta123!']);

        [$this->luis, $this->other] = Tenant::run($company->id, function () use ($company) {
            $role = Role::where('name', 'Empleado')->value('id');

            return [
                User::factory()->create(['company_id' => $company->id, 'role_id' => $role]),
                User::factory()->create(['company_id' => $company->id, 'role_id' => $role]),
            ];
        });
    }

    protected function tearDown(): void
    {
        Tenant::clear();
        parent::tearDown();
    }

    public function test_send_image_and_file_and_download_them(): void
    {
        Sanctum::actingAs($this->ana);

        $this->post('/api/notifications/send', [
            'receiver_id' => $this->luis->id,
            'file' => UploadedFile::fake()->image('foto.png', 40, 40),
        ], ["Accept" => "application/json"])->assertCreated();

        $this->post('/api/notifications/send', [
            'receiver_id' => $this->luis->id,
            'content' => 'Te paso el informe',
            'file' => UploadedFile::fake()->create('informe.pdf', 200, 'application/pdf'),
        ], ["Accept" => "application/json"])->assertCreated();

        $image = ChatAttachment::where('original_name', 'foto.png')->firstOrFail();
        $pdf = ChatAttachment::where('original_name', 'informe.pdf')->firstOrFail();
        Storage::disk('local')->assertExists($image->path);

        // the recipient sees both in the conversation and can open them
        Sanctum::actingAs($this->luis);
        $messages = $this->getJson("/api/notifications/conversation/{$this->ana->id}")->assertOk()->json();
        $this->assertSame('image', $messages[0]['attachment']['kind']);
        $this->assertSame('file', $messages[1]['attachment']['kind']);
        $this->assertSame('Te paso el informe', $messages[1]['content']);

        $this->get("/api/notifications/attachments/{$image->id}?inline=1")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get("/api/notifications/attachments/{$pdf->id}")->assertOk()->assertDownload('informe.pdf');

        // conversation preview names the attachment when there is no text
        Sanctum::actingAs($this->ana);
        $convs = $this->getJson('/api/notifications/conversations')->assertOk()->json('conversations');
        $this->assertSame('Te paso el informe', $convs[0]['last_message']);

        // a third person can't download it
        Sanctum::actingAs($this->other);
        $this->get("/api/notifications/attachments/{$pdf->id}")->assertNotFound();
    }

    public function test_attachment_rules(): void
    {
        Sanctum::actingAs($this->ana);

        // nothing to send
        $this->postJson('/api/notifications/send', ['receiver_id' => $this->luis->id])->assertStatus(422);

        // too big (over 10 MB)
        $this->post('/api/notifications/send', [
            'receiver_id' => $this->luis->id,
            'file' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        // type not allowed
        $this->post('/api/notifications/send', [
            'receiver_id' => $this->luis->id,
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, ChatAttachment::count());
    }
}
