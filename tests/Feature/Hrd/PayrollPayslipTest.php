<?php

namespace Tests\Feature\Hrd;

use App\Models\AttendanceReview;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPayslipTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraftEntry(): PayrollEntry
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
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
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        // A payroll eligible for approval needs a complete, reviewed attendance day.
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'present',
            'source_snapshot' => [], 'schedule_snapshot' => [],
            'correction_reason' => 'Kehadiran diverifikasi HRD untuk fixture payroll.',
            'validated_by' => $hrd->id, 'validated_at' => now(),
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();

        return PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();
    }

    private function approveEntryRun(PayrollEntry $entry): void
    {
        $run = $entry->run;
        $hrd = User::find($run->created_by);
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run));

        $manager = User::factory()->create(['company_id' => $run->period->company_id, 'role' => 'admin']);
        $this->actingAs($manager)->patch(route('manager.payroll.approve', $run), ['note' => null]);
    }

    public function test_hrd_cannot_publish_a_payslip_before_manager_approval(): void
    {
        $entry = $this->makeDraftEntry();
        $hrd = User::find($entry->run->created_by);

        $this->actingAs($hrd)
            ->post(route('hrd.hasil-payroll.entri.slip', $entry))
            ->assertStatus(422);

        $this->assertSame(0, PayrollPayslip::count());
    }

    public function test_hrd_can_publish_a_payslip_after_approval(): void
    {
        $entry = $this->makeDraftEntry();
        $hrd = User::find($entry->run->created_by);
        $this->approveEntryRun($entry);

        $this->actingAs($hrd)
            ->post(route('hrd.hasil-payroll.entri.slip', $entry))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $payslip = PayrollPayslip::where('payroll_entry_id', $entry->id)->firstOrFail();
        $this->assertSame(1, $payslip->version);
        $this->assertSame('published', $payslip->status);
        $this->assertSame($hrd->id, $payslip->published_by);
        $this->assertNotNull($payslip->published_at);

        $entry->refresh();
        $this->assertSame((string) $entry->gross_amount, $payslip->snapshot['gross_amount']);
        $this->assertSame((string) $entry->net_amount, $payslip->snapshot['net_amount']);
        $this->assertNotEmpty($payslip->snapshot['lines']);
    }

    public function test_publishing_again_supersedes_the_previous_version(): void
    {
        $entry = $this->makeDraftEntry();
        $hrd = User::find($entry->run->created_by);
        $this->approveEntryRun($entry);

        $this->actingAs($hrd)->post(route('hrd.hasil-payroll.entri.slip', $entry));
        $first = PayrollPayslip::where('payroll_entry_id', $entry->id)->firstOrFail();

        $this->actingAs($hrd)->post(route('hrd.hasil-payroll.entri.slip', $entry));
        $second = PayrollPayslip::where('payroll_entry_id', $entry->id)->where('version', 2)->firstOrFail();

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('published', $second->status);
        $this->assertNotSame($first->number, $second->number);
    }

    public function test_karyawan_and_manager_cannot_publish_a_payslip(): void
    {
        $entry = $this->makeDraftEntry();
        $this->approveEntryRun($entry);

        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($karyawan)->post(route('hrd.hasil-payroll.entri.slip', $entry))->assertForbidden();

        $manager = User::factory()->create(['role' => 'admin']);
        $this->actingAs($manager)->post(route('hrd.hasil-payroll.entri.slip', $entry))->assertForbidden();
    }

    public function test_hrd_cannot_publish_a_payslip_for_another_companys_entry(): void
    {
        $entry = $this->makeDraftEntry();
        $this->approveEntryRun($entry);

        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($otherHrd)
            ->post(route('hrd.hasil-payroll.entri.slip', $entry))
            ->assertForbidden();

        $this->assertSame(0, PayrollPayslip::count());
    }
}
