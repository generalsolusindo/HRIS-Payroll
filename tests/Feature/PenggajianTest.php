<?php

namespace Tests\Feature;

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
use Illuminate\Support\Str;
use Tests\TestCase;

class PenggajianTest extends TestCase
{
    use RefreshDatabase;

    private function makePublishedPayslip(User $employee): PayrollPayslip
    {
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id]);
        $division = Division::create(['company_id' => $employee->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $employee->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $employee->company_id, 'code' => '2026-09', 'name' => 'September 2026',
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
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run));
        $manager = User::factory()->create(['role' => 'admin', 'company_id' => $employee->company_id]);
        $this->actingAs($manager)->patch(route('manager.payroll.approve', $run), ['note' => null]);

        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();
        $this->actingAs($hrd)->post(route('hrd.hasil-payroll.entri.slip', $entry));

        return PayrollPayslip::where('payroll_entry_id', $entry->id)->firstOrFail();
    }

    public function test_employee_sees_their_own_published_payslip(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);

        $response = $this->actingAs($employee)
            ->get(route('penggajian'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('penggajian'));

        $ids = collect($response->viewData('page')['props']['payslips'])->pluck('id');
        $this->assertTrue($ids->contains($payslip->id));
    }

    public function test_employee_does_not_see_someone_elses_payslip_in_their_list(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $otherEmployee = User::factory()->create(['role' => 'karyawan']);
        $this->makePublishedPayslip($otherEmployee);

        $response = $this->actingAs($employee)
            ->get(route('penggajian'))
            ->assertOk();

        $this->assertCount(0, $response->viewData('page')['props']['payslips']);
    }

    public function test_employee_can_view_their_own_payslip_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);

        $this->actingAs($employee)
            ->get(route('penggajian.show', $payslip))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('penggajian-detail')
                ->where('payslip.id', $payslip->id)
            );
    }

    public function test_employee_cannot_view_someone_elses_payslip_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $otherEmployee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($otherEmployee);

        $this->actingAs($employee)
            ->get(route('penggajian.show', $payslip))
            ->assertForbidden();
    }

    public function test_hrd_can_view_any_employees_payslip_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);

        $viewerHrd = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($viewerHrd)
            ->get(route('penggajian.show', $payslip))
            ->assertOk();
    }

    public function test_published_slip_totals_remain_consistent_through_partial_payment_and_void(): void
    {
        // The helper calculates as HRD, submits, approves as Manager, then publishes.
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);
        $snapshot = $payslip->snapshot;
        $entry = $payslip->entry;
        $run = $entry->run;
        $finance = User::factory()->create(['role' => 'finance', 'company_id' => $employee->company_id]);

        $earnings = collect($snapshot['lines'])->where('type', 'earning')->sum('amount');
        $deductions = collect($snapshot['lines'])->where('type', 'deduction')->sum('amount');
        $this->assertSame('3000000.00', number_format($earnings - $deductions, 2, '.', ''));
        $this->assertSame($entry->net_amount, $snapshot['net_amount']);
        $this->assertSame($run->net_total, $snapshot['net_amount']);

        $this->actingAs($finance)->patch(route('finance.payroll.kunci', $run))
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        foreach (['1000000', '2000000'] as $index => $amount) {
            $this->actingAs($finance)->post(route('finance.entri.bayar', $entry), [
                'amount' => $amount,
                'method' => 'bank_transfer',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect()->assertSessionDoesntHaveErrors();

            $this->assertSame($index === 0 ? 'partially_paid' : 'paid', $run->fresh()->status);
        }

        $this->assertSame('3000000.00', number_format((float) $entry->payments()->where('status', 'confirmed')->sum('amount'), 2, '.', ''));
        $lastPayment = $entry->payments()->latest('id')->firstOrFail();
        $this->actingAs($finance)->patch(route('finance.pembayaran.batalkan', $lastPayment), [
            'note' => 'Koreksi pencatatan pembayaran kedua.',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('partially_paid', $run->fresh()->status);
        $this->assertSame($snapshot, $payslip->fresh()->snapshot);
        $this->actingAs($employee)->get(route('penggajian.show', $payslip))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('penggajian-detail')
            ->where('payslip.snapshot.net_amount', '3000000.00')
            ->where('payslip.snapshot.gross_amount', $snapshot['gross_amount'])
            ->where('payslip.snapshot.deduction_amount', $snapshot['deduction_amount'])
            );
    }

    public function test_a_superseded_payslip_no_longer_appears_in_the_employees_list(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $firstPayslip = $this->makePublishedPayslip($employee);

        $entry = $firstPayslip->entry;
        $hrd = User::find($entry->run->created_by);
        $this->actingAs($hrd)->post(route('hrd.hasil-payroll.entri.slip', $entry));

        $response = $this->actingAs($employee)->get(route('penggajian'))->assertOk();
        $numbers = collect($response->viewData('page')['props']['payslips'])->pluck('number');

        $this->assertFalse($numbers->contains($firstPayslip->number));
        $this->assertSame('superseded', $firstPayslip->fresh()->status);
    }
}
