<?php

namespace Tests\Feature\Hrd;

use App\Models\AttendanceReview;
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

        // A payroll eligible for approval needs a complete, reviewed attendance day.
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'present',
            'source_snapshot' => [], 'schedule_snapshot' => [],
            'correction_reason' => 'Kehadiran diverifikasi HRD untuk fixture payroll.',
            'validated_by' => $hrd->id, 'validated_at' => now(),
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

    public function test_incomplete_attendance_blocks_submission_and_refresh_rebuilds_corrected_draft(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);
        $run = $period->runs()->firstOrFail();
        $review = AttendanceReview::firstOrFail();
        $review->update(['classification' => 'unresolved', 'status' => 'draft', 'version' => 2]);
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run))->assertSessionHasErrors('readiness');
        $this->assertSame('draft', $run->fresh()->status);
        $this->assertSame(0, $run->approvals()->count());
        $review->update(['classification' => 'present', 'status' => 'validated', 'version' => 3]);
        // The old snapshot must still be rejected even after the review is fixed.
        $this->patch(route('hrd.hasil-payroll.ajukan', $run))->assertSessionHasErrors('readiness');
        $this->post(route('hrd.periode-gaji.perbarui-draft', $period))->assertSessionDoesntHaveErrors();
        $newRun = $period->runs()->latest('version')->firstOrFail();
        $this->assertSame(2, $newRun->version);
        $this->assertSame('superseded', $run->fresh()->status);
        $this->patch(route('hrd.hasil-payroll.ajukan', $newRun))->assertSessionDoesntHaveErrors();
        $this->assertSame('submitted', $newRun->fresh()->status);
        $this->post(route('hrd.periode-gaji.perbarui-draft', $period))->assertSessionHasErrors('period');
    }

    public function test_missing_employee_future_window_and_inconsistent_total_block_submission(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);
        $run = $period->runs()->firstOrFail();
        $extra = User::factory()->create(['role' => 'karyawan', 'status' => 'active', 'company_id' => $hrd->company_id]);
        $this->patch(route('hrd.hasil-payroll.ajukan', $run))->assertSessionHasErrors('readiness');
        $extra->forceFill(['status' => 'inactive'])->save();
        $period->update(['attendance_ends_on' => today()->addDay()]);
        $this->patch(route('hrd.hasil-payroll.ajukan', $run))->assertSessionHasErrors('readiness');
        $period->update(['attendance_ends_on' => '2026-09-28']);
        $run->update(['net_total' => '1.00']);
        $this->patch(route('hrd.hasil-payroll.ajukan', $run))->assertSessionHasErrors('readiness');
        $this->assertSame('draft', $run->fresh()->status);
    }

    public function test_another_company_cannot_refresh_a_draft(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->makeCalculatedPeriod($hrd);
        $other = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($other)->post(route('hrd.periode-gaji.perbarui-draft', $period))->assertForbidden();
        $this->assertSame(1, $period->runs()->count());
    }
}
