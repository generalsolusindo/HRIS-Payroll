<?php

namespace Tests\Feature\Services;

use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\NavBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavBadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaveRequest(User $employee, array $overrides = []): LeaveRequest
    {
        return LeaveRequest::create(array_merge([
            'request_number' => 'IZN-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => $employee->id,
            'type' => 'temporary_exit',
            'leave_date' => today(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'status' => 'pending',
        ], $overrides));
    }

    public function test_hrd_sees_a_badge_for_a_pending_leave_request_in_their_own_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['role' => 'karyawan', 'company_id' => $hrd->company_id]);
        $this->makeLeaveRequest($employee);

        $badges = app(NavBadgeService::class)->pendingBadges($hrd);

        $this->assertTrue($badges['hrd_izin']);
    }

    public function test_the_hrd_izin_badge_ignores_pending_requests_from_another_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherCompanyEmployee = User::factory()->create(['role' => 'karyawan']);
        $this->makeLeaveRequest($otherCompanyEmployee);

        $badges = app(NavBadgeService::class)->pendingBadges($hrd);

        $this->assertFalse($badges['hrd_izin']);
    }

    public function test_opening_the_izin_approval_page_clears_the_badge_until_something_new_arrives(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['role' => 'karyawan', 'company_id' => $hrd->company_id]);
        $this->makeLeaveRequest($employee);

        $service = app(NavBadgeService::class);
        $this->assertTrue($service->pendingBadges($hrd)['hrd_izin']);

        $this->actingAs($hrd)->get(route('hrd.izin.index'))->assertOk();
        $this->assertFalse($service->pendingBadges($hrd)['hrd_izin']);

        // A newer request after that visit must light the dot back up.
        $this->travel(1)->second();
        $this->makeLeaveRequest($employee);
        $this->assertTrue($service->pendingBadges($hrd)['hrd_izin']);
    }

    public function test_hrd_pending_users_badge_is_not_scoped_to_one_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        User::factory()->create(['role' => 'karyawan', 'status' => 'pending']);

        $badges = app(NavBadgeService::class)->pendingBadges($hrd);

        $this->assertTrue($badges['hrd_pending_users']);
    }

    public function test_manager_sees_badges_for_pending_izin_and_payroll_in_their_own_company(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'karyawan', 'company_id' => $manager->company_id]);
        $this->makeLeaveRequest($employee, ['status' => 'pending_manager']);

        $badges = app(NavBadgeService::class)->pendingBadges($manager);

        $this->assertTrue($badges['manager_izin']);
        $this->assertFalse($badges['manager_payroll']);
    }

    public function test_karyawan_gets_a_badge_once_their_overtime_request_is_decided(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $request = OvertimeRequest::create([
            'user_id' => $employee->id,
            'company_id' => $employee->company_id,
            'starts_at' => '2026-09-28 18:00:00',
            'ends_at' => '2026-09-28 21:00:00',
            'reason' => 'Lembur',
            'status' => 'submitted',
        ]);

        $service = app(NavBadgeService::class);
        $this->assertFalse($service->pendingBadges($employee)['karyawan_lembur']);

        $request->update(['status' => 'approved']);
        $this->assertTrue($service->pendingBadges($employee)['karyawan_lembur']);

        $this->actingAs($employee)->get(route('lembur.index'))->assertOk();
        $this->assertFalse($service->pendingBadges($employee)['karyawan_lembur']);
    }
}
