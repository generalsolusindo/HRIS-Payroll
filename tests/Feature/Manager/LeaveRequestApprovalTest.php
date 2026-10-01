<?php

namespace Tests\Feature\Manager;

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
            'status' => 'pending_manager',
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd'])->id,
            'hrd_processed_at' => now(),
            'hrd_decision' => 'approved',
        ], $overrides));
    }

    public function test_hrd_cannot_access_manager_approval()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('manager.izin.index'))
            ->assertForbidden();
    }

    public function test_karyawan_cannot_access_manager_approval()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('manager.izin.index'))
            ->assertForbidden();
    }

    public function test_manager_sees_requests_awaiting_their_decision()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest();

        $response = $this->actingAs($manager)
            ->get(route('manager.izin.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('manager/leave-request-approval'));

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($leaveRequest->id, $ids);
    }

    public function test_manager_approve_is_final()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), ['note' => 'Disetujui.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame($manager->id, $leaveRequest->processed_by);
        $this->assertSame('Disetujui.', $leaveRequest->approval_note);
    }

    public function test_manager_reject_is_final()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Tidak disetujui.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();

        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame($manager->id, $leaveRequest->processed_by);
        $this->assertSame('Tidak disetujui.', $leaveRequest->rejection_reason);
    }

    public function test_reject_requires_a_note()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), [])
            ->assertSessionHasErrors('note');

        $this->assertSame('pending_manager', $leaveRequest->fresh()->status);
    }

    public function test_manager_cannot_process_a_request_hrd_has_not_approved_yet()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest(['status' => 'pending', 'hrd_processed_by' => null, 'hrd_decision' => null]);

        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), [])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_cannot_process_an_already_finalized_request()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest(['status' => 'approved']);

        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Coba ubah.'])
            ->assertSessionHasErrors('status');

        $this->assertSame('approved', $leaveRequest->fresh()->status);
    }
}
