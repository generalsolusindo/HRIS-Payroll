<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveRequestAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('leave_attachments');
    }

    private function makeAttachment(User $owner): LeaveRequestAttachment
    {
        $leaveRequest = LeaveRequest::create([
            'request_number' => 'IZN-2026-000001',
            'user_id' => $owner->id,
            'type' => 'sick',
            'leave_date' => today(),
            'contactable' => true,
            'status' => 'pending',
        ]);

        $file = UploadedFile::fake()->create('surat-dokter.pdf', 100, 'application/pdf');
        $path = $file->store((string) $leaveRequest->id, 'leave_attachments');

        return LeaveRequestAttachment::create([
            'leave_request_id' => $leaveRequest->id,
            'file_name' => 'surat-dokter.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => $file->getSize(),
        ]);
    }

    public function test_guest_cannot_access_an_attachment()
    {
        $owner = User::factory()->create();
        $attachment = $this->makeAttachment($owner);

        $this->get(route('izin.attachments.show', $attachment))
            ->assertRedirect(route('login'));
    }

    public function test_owner_can_download_their_own_attachment()
    {
        $owner = User::factory()->create();
        $attachment = $this->makeAttachment($owner);

        $this->actingAs($owner)
            ->get(route('izin.attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_other_employee_cannot_access_someone_elses_attachment()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $attachment = $this->makeAttachment($owner);

        $this->actingAs($other)
            ->get(route('izin.attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_hrd_can_access_any_attachment()
    {
        $owner = User::factory()->create();
        $hrd = User::factory()->create(['role' => 'hrd']);
        $attachment = $this->makeAttachment($owner);

        $this->actingAs($hrd)
            ->get(route('izin.attachments.show', $attachment))
            ->assertOk();
    }

    public function test_manager_can_access_any_attachment()
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create(['role' => 'admin']);
        $attachment = $this->makeAttachment($owner);

        $this->actingAs($manager)
            ->get(route('izin.attachments.show', $attachment))
            ->assertOk();
    }

    public function test_missing_file_on_disk_returns_404_instead_of_crashing()
    {
        $owner = User::factory()->create();
        $attachment = $this->makeAttachment($owner);

        Storage::disk('leave_attachments')->delete($attachment->file_path);

        $this->actingAs($owner)
            ->get(route('izin.attachments.show', $attachment))
            ->assertNotFound();
    }
}
