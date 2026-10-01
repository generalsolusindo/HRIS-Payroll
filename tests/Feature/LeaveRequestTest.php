<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('leave_attachments');
    }

    public function test_guest_cannot_submit_a_leave_request()
    {
        $response = $this->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_employee_can_submit_a_temporary_exit_request()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'reason' => 'Urusan bank.',
        ]);

        $response->assertSessionDoesntHaveErrors()->assertRedirect();

        $leaveRequest = LeaveRequest::first();

        $this->assertNotNull($leaveRequest);
        $this->assertSame($user->id, $leaveRequest->user_id);
        $this->assertSame('temporary_exit', $leaveRequest->type);
        $this->assertSame('pending', $leaveRequest->status);
        $this->assertMatchesRegularExpression('/^IZN-\d{4}-\d{6}$/', $leaveRequest->request_number);
    }

    public function test_invalid_submission_is_rejected_with_validation_errors()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'type' => 'half_day',
            'leave_date' => today()->toDateString(), // half_day requires H+1
            'start_time' => '13:00',
        ]);

        $response->assertSessionHasErrors('leave_date');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_sick_leave_over_one_day_requires_an_attachment()
    {
        $user = User::factory()->create();
        $this->travelTo(today()->setTime(8, 0));

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'type' => 'sick',
            'leave_date' => today()->toDateString(),
            'contactable' => true,
            'duration' => 2,
        ]);

        $response->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_sick_leave_with_attachment_is_stored_privately()
    {
        $user = User::factory()->create();
        $this->travelTo(today()->setTime(8, 0));
        $file = UploadedFile::fake()->create('surat-dokter.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'type' => 'sick',
            'leave_date' => today()->toDateString(),
            'contactable' => true,
            'duration' => 2,
            'attachments' => [$file],
        ]);

        $response->assertSessionDoesntHaveErrors();

        $leaveRequest = LeaveRequest::first();
        $attachment = LeaveRequestAttachment::first();

        $this->assertNotNull($attachment);
        $this->assertSame($leaveRequest->id, $attachment->leave_request_id);
        $this->assertSame('surat-dokter.pdf', $attachment->file_name);
        Storage::disk('leave_attachments')->assertExists($attachment->file_path);
    }

    public function test_each_submission_gets_a_unique_request_number()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
        ]);

        $this->actingAs($user)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '14:00',
            'estimated_arrival' => '15:00',
        ]);

        $numbers = LeaveRequest::pluck('request_number')->all();

        $this->assertCount(2, array_unique($numbers));
    }
}
