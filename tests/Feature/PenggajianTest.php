<?php

namespace Tests\Feature;

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
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run));
        $manager = User::factory()->create(['role' => 'admin']);
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
