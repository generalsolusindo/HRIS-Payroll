<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodDeletionRequest;
use App\Models\PayrollPolicy;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->withEnrolledFace()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_karyawan_gets_the_clock_in_dashboard_not_the_hrd_summary(): void
    {
        $user = User::factory()->withEnrolledFace()->create(['role' => 'karyawan']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hrdSummary', null)
                ->has('todayAttendance')
                ->has('officeLocations')
            );
    }

    public function test_hrd_gets_a_company_wide_attendance_summary_instead(): void
    {
        $hrd = User::factory()->withEnrolledFace()->create(['role' => 'hrd']);

        $onTime = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        Attendance::create(['user_id' => $onTime->id, 'tanggal' => today(), 'clock_in_time' => now(), 'clock_in_status' => 'tepat_waktu']);

        $late = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        Attendance::create(['user_id' => $late->id, 'tanggal' => today(), 'clock_in_time' => now(), 'clock_in_status' => 'telat']);

        // Not clocked in yet today — still counts toward "total", not "sudah absen".
        User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // Must not be counted at all: wrong role, and inactive status.
        User::factory()->create(['role' => 'hrd', 'status' => 'active']);
        User::factory()->create(['role' => 'karyawan', 'status' => 'pending']);

        $this->actingAs($hrd)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hrdSummary.total_karyawan', 3)
                ->where('hrdSummary.sudah_absen', 2)
                ->where('hrdSummary.belum_absen', 1)
                ->where('hrdSummary.telat', 1)
                ->where('hrdSummary.pending_users', 1)
                ->missing('todayAttendance')
            );
    }

    public function test_manager_gets_an_approval_summary_instead_of_the_clock_in_dashboard(): void
    {
        $manager = User::factory()->withEnrolledFace()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'role' => 'karyawan', 'status' => 'active']);
        LeaveRequest::create([
            'request_number' => 'REQ-DASH-1', 'user_id' => $employee->id, 'type' => 'sick',
            'leave_date' => today(), 'status' => 'pending_manager',
        ]);
        $period = PayrollPeriod::create([
            'company_id' => $manager->company_id, 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-10-01', 'attendance_ends_on' => '2026-10-17',
        ]);
        $policy = PayrollPolicy::create([
            'company_id' => $manager->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => [],
        ]);
        PayrollRun::create(['payroll_period_id' => $period->id, 'payroll_policy_id' => $policy->id, 'version' => 1, 'status' => 'submitted', 'policy_snapshot' => [], 'company_snapshot' => []]);
        PayrollPeriodDeletionRequest::create([
            'payroll_period_id' => $period->id, 'requested_by' => $manager->id,
            'requested_by_snapshot' => ['name' => $manager->name], 'status' => 'pending',
            'reason' => 'Dibuat keliru.', 'requested_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('managerSummary.pending_leave', 1)
                ->where('managerSummary.pending_payroll', 1)
                ->where('managerSummary.pending_period_deletion', 1)
                ->missing('todayAttendance')
                ->missing('hrdSummary')
            );
    }

    public function test_finance_gets_a_pending_payment_summary_instead_of_the_clock_in_dashboard(): void
    {
        $finance = User::factory()->withEnrolledFace()->create(['role' => 'finance']);
        $period = PayrollPeriod::create([
            'company_id' => $finance->company_id, 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-10-01', 'attendance_ends_on' => '2026-10-17',
        ]);
        $policy = PayrollPolicy::create([
            'company_id' => $finance->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => [],
        ]);
        PayrollRun::create(['payroll_period_id' => $period->id, 'payroll_policy_id' => $policy->id, 'version' => 1, 'status' => 'approved', 'policy_snapshot' => [], 'company_snapshot' => []]);

        $this->actingAs($finance)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('financeSummary.pending_payment', 1)
                ->missing('todayAttendance')
                ->missing('hrdSummary')
            );
    }
}
