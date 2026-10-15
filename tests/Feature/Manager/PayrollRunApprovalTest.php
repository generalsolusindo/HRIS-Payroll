<?php

namespace Tests\Feature\Manager;

use App\Models\AttendanceReview;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRunApprovalTest extends TestCase
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

        return $run->fresh();
    }

    /**
     * @return array{0: User, 1: PayrollRun}
     */
    private function makeManagerAndSubmittedRun(): array
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);

        return [$manager, $this->makeSubmittedRun($hrd)];
    }

    public function test_hrd_and_karyawan_cannot_access_manager_payroll_approval(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($hrd)->get(route('manager.payroll.index'))->assertForbidden();

        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($karyawan)->get(route('manager.payroll.index'))->assertForbidden();
    }

    public function test_manager_sees_only_submitted_runs_in_their_own_company(): void
    {
        [$manager, $submitted] = $this->makeManagerAndSubmittedRun();
        [, $draft] = $this->makeManagerAndSubmittedRun();
        $draft->update(['status' => 'draft']);

        $response = $this->actingAs($manager)
            ->get(route('manager.payroll.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('manager/payroll-run-approval'));

        $ids = collect($response->viewData('page')['props']['runs'])->pluck('id');
        $this->assertTrue($ids->contains($submitted->id));
        $this->assertFalse($ids->contains($draft->id));
    }

    public function test_manager_cannot_see_another_companys_submitted_run_in_the_list(): void
    {
        [$manager] = $this->makeManagerAndSubmittedRun();
        [, $otherCompanysRun] = $this->makeManagerAndSubmittedRun();

        $response = $this->actingAs($manager)->get(route('manager.payroll.index'))->assertOk();

        $ids = collect($response->viewData('page')['props']['runs'])->pluck('id');
        $this->assertFalse($ids->contains($otherCompanysRun->id));
    }

    public function test_manager_can_approve_a_submitted_run(): void
    {
        [$manager, $run] = $this->makeManagerAndSubmittedRun();

        $this->actingAs($manager)
            ->patch(route('manager.payroll.approve', $run), ['note' => 'Sudah dicek, OK.'])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $run->refresh();
        $this->assertSame('approved', $run->status);
        $this->assertNotNull($run->approved_at);

        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $run->id, 'action' => 'approved',
            'from_status' => 'submitted', 'to_status' => 'approved',
            'actor_id' => $manager->id, 'note' => 'Sudah dicek, OK.',
        ]);
    }

    public function test_manager_cannot_approve_another_companys_run(): void
    {
        [$manager] = $this->makeManagerAndSubmittedRun();
        [, $otherCompanysRun] = $this->makeManagerAndSubmittedRun();

        $this->actingAs($manager)
            ->patch(route('manager.payroll.approve', $otherCompanysRun), ['note' => null])
            ->assertForbidden();

        $this->assertSame('submitted', $otherCompanysRun->fresh()->status);
    }

    public function test_manager_can_request_revision_with_a_note(): void
    {
        [$manager, $run] = $this->makeManagerAndSubmittedRun();

        $this->actingAs($manager)
            ->patch(route('manager.payroll.revisi', $run), ['note' => 'Potongan telat belum sesuai.'])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $run->refresh();
        $this->assertSame('revision_requested', $run->status);
        $this->assertSame('Potongan telat belum sesuai.', $run->revision_reason);

        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $run->id, 'action' => 'revision_requested',
            'from_status' => 'submitted', 'to_status' => 'revision_requested',
        ]);
    }

    public function test_manager_cannot_request_revision_on_another_companys_run(): void
    {
        [$manager] = $this->makeManagerAndSubmittedRun();
        [, $otherCompanysRun] = $this->makeManagerAndSubmittedRun();

        $this->actingAs($manager)
            ->patch(route('manager.payroll.revisi', $otherCompanysRun), ['note' => 'x'])
            ->assertForbidden();
    }

    public function test_revision_request_requires_a_note(): void
    {
        [$manager, $run] = $this->makeManagerAndSubmittedRun();

        $this->actingAs($manager)
            ->patch(route('manager.payroll.revisi', $run), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame('submitted', $run->fresh()->status);
    }

    public function test_manager_cannot_act_on_a_run_that_is_not_submitted(): void
    {
        [$manager, $run] = $this->makeManagerAndSubmittedRun();
        $run->update(['status' => 'draft']);

        $this->actingAs($manager)
            ->patch(route('manager.payroll.approve', $run), ['note' => null])
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $run->fresh()->status);
    }

    public function test_hrd_cannot_approve_or_request_revision(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $run = $this->makeSubmittedRun($hrd);

        $this->actingAs($hrd)->patch(route('manager.payroll.approve', $run), ['note' => null])->assertForbidden();
        $this->actingAs($hrd)->patch(route('manager.payroll.revisi', $run), ['note' => 'x'])->assertForbidden();
    }

    public function test_manager_can_view_the_run_detail_through_their_own_route(): void
    {
        [$manager, $run] = $this->makeManagerAndSubmittedRun();

        $this->actingAs($manager)
            ->get(route('manager.payroll.show', $run))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/payroll-run-show')
                ->where('run.id', $run->id)
            );
    }

    public function test_manager_cannot_view_another_companys_run_detail(): void
    {
        [$manager] = $this->makeManagerAndSubmittedRun();
        [, $otherCompanysRun] = $this->makeManagerAndSubmittedRun();

        $this->actingAs($manager)
            ->get(route('manager.payroll.show', $otherCompanysRun))
            ->assertForbidden();
    }

    public function test_manager_must_request_revision_if_attendance_changed_after_submission(): void
    {
        [$manager, $run] = $this->makeManagerAndSubmittedRun();
        AttendanceReview::where('company_id', $manager->company_id)->update(['version' => 2]);
        $this->actingAs($manager)->patch(route('manager.payroll.approve', $run))->assertSessionHasErrors('readiness');
        $this->assertSame('submitted', $run->fresh()->status);
        $this->patch(route('manager.payroll.revisi', $run), ['note' => 'Absensi berubah, hitung ulang.'])->assertSessionDoesntHaveErrors();
        $this->assertSame('revision_requested', $run->fresh()->status);
    }
}
