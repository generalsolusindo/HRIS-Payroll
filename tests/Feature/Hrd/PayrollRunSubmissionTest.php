<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollApproval;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRunSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCalculatedPeriod(User $hrd): PayrollPeriod
    {
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

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        return $period;
    }

    public function test_hrd_can_submit_a_draft_run_for_approval(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);
        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();

        $this->actingAs($hrd)
            ->patch(route('hrd.hasil-payroll.ajukan', $run))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $run->refresh();
        $this->assertSame('submitted', $run->status);
        $this->assertNotNull($run->submitted_at);

        $approval = PayrollApproval::where('payroll_run_id', $run->id)->where('action', 'submitted')->firstOrFail();
        $this->assertSame('draft', $approval->from_status);
        $this->assertSame('submitted', $approval->to_status);
        $this->assertSame($hrd->id, $approval->actor_id);
    }

    public function test_a_non_draft_run_cannot_be_submitted_again(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);
        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $run->update(['status' => 'submitted']);

        $this->actingAs($hrd)
            ->patch(route('hrd.hasil-payroll.ajukan', $run))
            ->assertSessionHasErrors('status');

        // Status was set directly (not via submit()), so no approval row
        // was ever recorded, and the rejected request must not add one.
        $this->assertSame(0, PayrollApproval::where('payroll_run_id', $run->id)->count());
    }

    public function test_karyawan_and_manager_cannot_submit_a_run(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);
        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();

        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($karyawan)->patch(route('hrd.hasil-payroll.ajukan', $run))->assertForbidden();

        $manager = User::factory()->create(['role' => 'admin']);
        $this->actingAs($manager)->patch(route('hrd.hasil-payroll.ajukan', $run))->assertForbidden();
    }

    public function test_a_period_cannot_be_recalculated_unless_the_latest_run_needs_revision(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);

        // Still draft — recalculating now would silently discard it.
        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionHasErrors('period');

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $run->update(['status' => 'approved']);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionHasErrors('period');

        $this->assertSame(1, PayrollRun::where('payroll_period_id', $period->id)->count());
    }

    public function test_recalculating_after_a_revision_request_creates_a_new_version_and_supersedes_the_old_one(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);
        $firstRun = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $firstRun->update(['status' => 'revision_requested', 'revision_reason' => 'Tolong cek lagi potongannya.']);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertSame('superseded', $firstRun->fresh()->status);
        $this->assertSame(2, PayrollRun::where('payroll_period_id', $period->id)->count());

        $secondRun = PayrollRun::where('payroll_period_id', $period->id)->where('version', 2)->firstOrFail();
        $this->assertSame('draft', $secondRun->status);
        $this->assertSame($firstRun->id, $secondRun->supersedes_run_id);

        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $firstRun->id, 'action' => 'superseded',
            'from_status' => 'revision_requested', 'to_status' => 'superseded',
        ]);
    }
}
