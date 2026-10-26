<?php

namespace Tests\Feature\Manager;

use App\Models\AttendanceReview;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollRunHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmittedRun(User $hrd): PayrollRun
    {
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'present',
            'source_snapshot' => [], 'schedule_snapshot' => [],
            'correction_reason' => 'Kehadiran diverifikasi HRD untuk fixture payroll.',
            'validated_by' => $hrd->id, 'validated_at' => now(),
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run));

        return $run->fresh();
    }

    public function test_hrd_and_karyawan_cannot_access_manager_payroll_history(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($hrd)->get(route('manager.payroll.riwayat'))->assertForbidden();

        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($karyawan)->get(route('manager.payroll.riwayat'))->assertForbidden();
    }

    public function test_an_approved_run_still_appears_in_the_managers_history_after_leaving_the_approval_queue(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);
        $run = $this->makeSubmittedRun($hrd);

        $this->actingAs($manager)->patch(route('manager.payroll.approve', $run), []);

        // Gone from the live approval queue...
        $this->actingAs($manager)
            ->get(route('manager.payroll.index'))
            ->assertInertia(fn ($page) => $page->where('runs', fn ($runs) => collect($runs)->where('id', $run->id)->isEmpty()));

        // ...but still findable in history, with its current status.
        $this->actingAs($manager)
            ->get(route('manager.payroll.riwayat'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('manager/payroll-run-history')
                ->where('runs.data', fn ($runs) => collect($runs)->contains(
                    fn ($r) => $r['id'] === $run->id && $r['status'] === 'approved'
                ))
            );
    }

    public function test_history_reflects_paid_status_once_finance_finishes_paying(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);
        $finance = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'finance']);
        $run = $this->makeSubmittedRun($hrd);

        $this->actingAs($manager)->patch(route('manager.payroll.approve', $run), []);
        $this->actingAs($finance)->patch(route('finance.payroll.kunci', $run));

        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();
        $this->actingAs($finance)->post(route('finance.entri.bayar', $entry), [
            'amount' => (string) $entry->net_amount,
            'method' => 'bank_transfer',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($manager)
            ->get(route('manager.payroll.riwayat'))
            ->assertInertia(fn ($page) => $page
                ->where('runs.data', fn ($runs) => collect($runs)->contains(
                    fn ($r) => $r['id'] === $run->id && $r['status'] === 'paid'
                ))
            );
    }

    public function test_history_is_scoped_to_the_managers_own_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);
        $run = $this->makeSubmittedRun($hrd);

        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $otherRun = $this->makeSubmittedRun($otherHrd);

        $this->actingAs($manager)
            ->get(route('manager.payroll.riwayat'))
            ->assertInertia(fn ($page) => $page
                ->where('runs.data', fn ($runs) => collect($runs)->contains('id', $run->id)
                    && collect($runs)->doesntContain('id', $otherRun->id))
            );
    }
}
