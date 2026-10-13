<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestReadTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaveRequest(User $owner, array $overrides = []): LeaveRequest
    {
        return LeaveRequest::create(array_merge([
            'request_number' => 'IZN-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => $owner->id,
            'type' => 'temporary_exit',
            'leave_date' => today(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'status' => 'pending',
        ], $overrides));
    }

    public function test_guest_cannot_view_leave_request_history()
    {
        $this->get(route('izin'))->assertRedirect(route('login'));
    }

    public function test_employee_only_sees_their_own_leave_requests()
    {
        $employee = User::factory()->create();
        $other = User::factory()->create();

        $own = $this->makeLeaveRequest($employee);
        $this->makeLeaveRequest($other);

        $response = $this->actingAs($employee)
            ->get(route('izin'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('izin'));

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');

        $this->assertContains($own->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_owner_can_view_their_own_leave_request_detail()
    {
        $employee = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($employee)
            ->get(route('izin.show', $leaveRequest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('izin-detail'));
    }

    public function test_other_employee_cannot_view_someone_elses_leave_request()
    {
        $employee = User::factory()->create();
        $other = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($other);

        $this->actingAs($employee)
            ->get(route('izin.show', $leaveRequest))
            ->assertForbidden();
    }

    public function test_hrd_can_view_their_own_companys_leave_request_detail_including_already_decided_ones()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee, ['status' => 'approved']);

        $this->actingAs($hrd)
            ->get(route('izin.show', $leaveRequest))
            ->assertOk();
    }

    public function test_hrd_cannot_view_another_companys_leave_request_detail()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($employee, ['status' => 'approved']);

        $this->actingAs($hrd)
            ->get(route('izin.show', $leaveRequest))
            ->assertForbidden();
    }

    public function test_manager_can_view_their_own_companys_leave_request_detail()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($manager)
            ->get(route('izin.show', $leaveRequest))
            ->assertOk();
    }

    public function test_manager_cannot_view_another_companys_leave_request_detail()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($manager)
            ->get(route('izin.show', $leaveRequest))
            ->assertForbidden();
    }
}
