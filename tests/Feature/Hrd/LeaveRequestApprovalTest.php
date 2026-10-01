<?php

namespace Tests\Feature\Hrd;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaveRequest(array $overrides = []): LeaveRequest
    {
        $employee = User::factory()->create();

        return LeaveRequest::create(array_merge([
            'request_number' => 'IZN-2026-000001',
            'user_id' => $employee->id,
            'type' => 'temporary_exit',
            'leave_date' => today(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'status' => 'pending',
        ], $overrides));
    }

    public function test_karyawan_cannot_access_leave_request_approval()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.izin.index'))
            ->assertForbidden();
    }

    public function test_hrd_sees_pending_leave_requests()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/leave-request-approval'));

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($leaveRequest->id, $ids);
    }

    public function test_hrd_approve_moves_to_pending_manager()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.approve', $leaveRequest), ['note' => 'Oke, silakan.'])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $leaveRequest->refresh();

        $this->assertSame('pending_manager', $leaveRequest->status);
        $this->assertSame($hrd->id, $leaveRequest->hrd_processed_by);
        $this->assertSame('approved', $leaveRequest->hrd_decision);
        $this->assertSame('Oke, silakan.', $leaveRequest->hrd_note);
        $this->assertNull($leaveRequest->processed_by);
    }

    public function test_hrd_reject_is_final_immediately()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Bukti tidak lengkap.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();

        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame($hrd->id, $leaveRequest->hrd_processed_by);
        $this->assertSame('rejected', $leaveRequest->hrd_decision);
        $this->assertSame($hrd->id, $leaveRequest->processed_by);
        $this->assertSame('Bukti tidak lengkap.', $leaveRequest->rejection_reason);
    }

    public function test_reject_requires_a_note()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.reject', $leaveRequest), [])
            ->assertSessionHasErrors('note');

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_cannot_process_an_already_processed_request()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest(['status' => 'rejected']);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.approve', $leaveRequest), [])
            ->assertSessionHasErrors('status');

        $this->assertSame('rejected', $leaveRequest->fresh()->status);
    }
}
