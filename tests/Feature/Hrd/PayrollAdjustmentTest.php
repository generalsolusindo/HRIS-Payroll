<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollAuditLog;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $hrd;

    private PayrollRun $run;

    private PayrollEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $this->hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $division = Division::create(['company_id' => $employee->company_id, 'name' => 'Operasional']);
        $component = PayrollComponent::create(['company_id' => $employee->company_id, 'code' => 'BASE', 'name' => 'Gaji', 'type' => 'earning']);
        $profile = EmployeePayrollProfile::create(['user_id' => $employee->id, 'company_id' => $employee->company_id, 'division_id' => $division->id, 'position_id' => $employee->position_id, 'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent']);
        EmployeeSalaryComponent::create(['employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $component->id, 'amount' => '3000000.01', 'basis' => 'monthly']);
        $period = PayrollPeriod::create(['company_id' => $employee->company_id, 'name' => 'September', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28']);
        $this->actingAs($this->hrd)->post(route('hrd.periode-gaji.hitung', $period))->assertSessionDoesntHaveErrors();
        $this->run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->entry = $this->run->entries()->firstOrFail();
    }

    private function payload(string $type = 'earning', string $amount = '100.10'): array
    {
        return ['type' => $type, 'name' => 'Koreksi gaji', 'amount' => $amount, 'reason' => 'Koreksi yang disetujui HRD', 'request_id' => (string) Str::uuid()];
    }

    public function test_adjustments_refresh_all_totals_and_record_audit_without_duplicate_retries(): void
    {
        $payload = $this->payload();
        foreach ([1, 2] as $attempt) {
            $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $payload)->assertRedirect()->assertSessionDoesntHaveErrors();
        }
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload('deduction', '50.05'))->assertSessionDoesntHaveErrors();
        $this->assertSame('3000100.11', $this->entry->fresh()->gross_amount);
        $this->assertSame('50.05', $this->entry->fresh()->deduction_amount);
        $this->assertSame('3000050.06', $this->entry->fresh()->net_amount);
        $this->assertSame('3000050.06', $this->run->fresh()->net_total);
        $this->assertSame(2, $this->entry->lines()->where('source', 'manual')->count());
        $this->assertSame(2, PayrollAuditLog::where('action', 'manual_added')->count());
        $this->get(route('hrd.hasil-payroll.show', $this->run))->assertInertia(fn ($page) => $page->where('divisions.0.net_total', '3000050.06')->where('capabilities.adjust', true));
    }

    public function test_cancellation_keeps_history_and_retry_does_not_recreate_amount(): void
    {
        $payload = $this->payload();
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $payload)->assertSessionDoesntHaveErrors();
        $line = $this->entry->lines()->where('source', 'manual')->firstOrFail();
        $this->delete(route('hrd.hasil-payroll.penyesuaian.destroy', $line), ['reason' => 'Salah nominal'])->assertSessionDoesntHaveErrors();
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $payload)->assertSessionDoesntHaveErrors();
        $this->assertSame('0.00', $line->fresh()->amount);
        $this->assertSame('3000000.01', $this->run->fresh()->net_total);
        $this->assertSame('Salah nominal', $line->fresh()->calculation_snapshot['cancellation_reason']);
        $this->assertDatabaseHas('payroll_audit_logs', ['entity_id' => $line->id, 'action' => 'manual_cancelled']);
    }

    public function test_negative_net_and_invalid_payload_roll_back_changes(): void
    {
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload('deduction', '3000000.02'))->assertSessionHasErrors('amount');
        $bad = $this->payload();
        $bad['amount'] = '1.001';
        $bad['reason'] = '';
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $bad)->assertSessionHasErrors(['amount', 'reason']);
        $this->assertSame(0, $this->entry->lines()->where('source', 'manual')->count());
        $this->assertSame(0, PayrollAuditLog::where('action', 'manual_added')->count());
        $this->assertSame('3000000.01', $this->run->fresh()->net_total);
    }

    public function test_non_draft_and_other_company_and_wrong_roles_cannot_adjust(): void
    {
        $payload = $this->payload();
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $payload)->assertSessionDoesntHaveErrors();
        $line = $this->entry->lines()->where('source', 'manual')->firstOrFail();
        foreach (['submitted', 'approved', 'locked', 'paid', 'revision_requested', 'superseded'] as $status) {
            $this->run->update(['status' => $status]);
            $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload())->assertSessionHasErrors('status');
            $this->delete(route('hrd.hasil-payroll.penyesuaian.destroy', $line), ['reason' => 'Test'])->assertSessionHasErrors('status');
        }
        $this->run->update(['status' => 'draft']);
        foreach (['hrd', 'admin', 'finance', 'karyawan'] as $role) {
            $other = User::factory()->create(['role' => $role]);
            $this->actingAs($other)->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload())->assertForbidden();
            $this->delete(route('hrd.hasil-payroll.penyesuaian.destroy', $line), ['reason' => 'Test'])->assertForbidden();
        }
    }

    public function test_revision_carries_manual_lines_without_mutating_previous_run(): void
    {
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload())->assertSessionDoesntHaveErrors();
        $this->run->update(['status' => 'revision_requested']);
        $this->post(route('hrd.periode-gaji.hitung', $this->run->period))->assertSessionDoesntHaveErrors();
        $newRun = PayrollRun::where('payroll_period_id', $this->run->payroll_period_id)->latest('version')->firstOrFail();
        $this->assertSame(2, $newRun->version);
        $this->assertSame('3000100.11', $newRun->net_total);
        $this->assertSame('3000100.11', $this->run->fresh()->net_total);
        $copy = $newRun->entries()->firstOrFail()->lines()->where('source', 'manual')->firstOrFail();
        $this->assertArrayHasKey('copied_from_line_id', $copy->calculation_snapshot);
    }

    public function test_existing_calculated_lines_cannot_be_cancelled(): void
    {
        $line = $this->entry->lines()->where('source', 'salary')->firstOrFail();
        $this->delete(route('hrd.hasil-payroll.penyesuaian.destroy', $line), ['reason' => 'Test'])->assertStatus(422);
        $this->assertSame('3000000.01', $this->entry->fresh()->net_amount);
    }

    public function test_retry_key_cannot_change_an_existing_adjustment(): void
    {
        $payload = $this->payload();
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $payload)->assertSessionDoesntHaveErrors();
        $payload['amount'] = '500';
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $payload)->assertSessionHasErrors('amount');
        $this->assertSame('3000100.11', $this->run->fresh()->net_total);
    }

    public function test_cancelling_income_that_would_make_net_negative_rolls_back(): void
    {
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload('earning', '100.00'))->assertSessionDoesntHaveErrors();
        $income = $this->entry->lines()->where('source', 'manual')->firstOrFail();
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload('deduction', '3000050.01'))->assertSessionDoesntHaveErrors();
        $this->delete(route('hrd.hasil-payroll.penyesuaian.destroy', $income), ['reason' => 'Koreksi'])->assertSessionHasErrors('amount');
        $this->assertSame('100.00', $income->fresh()->amount);
        $this->assertSame('50.00', $this->entry->fresh()->net_amount);
        $this->assertDatabaseMissing('payroll_audit_logs', ['entity_id' => $income->id, 'action' => 'manual_cancelled']);
    }

    public function test_revision_cannot_silently_drop_employee_with_manual_adjustments(): void
    {
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload())->assertSessionDoesntHaveErrors();
        $this->entry->employeePayrollProfile->salaryComponents()->delete();
        $this->run->update(['status' => 'revision_requested']);
        $this->post(route('hrd.periode-gaji.hitung', $this->run->period))->assertSessionHasErrors('period');
        $this->assertSame(1, PayrollRun::count());
        $this->assertSame('revision_requested', $this->run->fresh()->status);
        $this->assertSame('3000100.11', $this->run->fresh()->net_total);
    }

    public function test_adjusting_one_employee_keeps_other_employee_in_company_totals(): void
    {
        $employee = User::factory()->create(['company_id' => $this->hrd->company_id]);
        $otherEntry = $this->entry->replicate();
        $otherEntry->user_id = $employee->id;
        $otherEntry->save();
        $line = $this->entry->lines()->firstOrFail()->replicate();
        $line->payroll_entry_id = $otherEntry->id;
        $line->save();
        $this->post(route('hrd.hasil-payroll.penyesuaian.store', $this->entry), $this->payload())->assertSessionDoesntHaveErrors();
        $this->assertSame('6000100.12', $this->run->fresh()->net_total);
        $this->assertSame('3000000.01', $otherEntry->fresh()->net_amount);
        $this->assertSame(2, $this->run->fresh()->employee_count);
    }

    public function test_calculator_rejects_a_deduction_in_salary_profile_instead_of_adding_it_to_gross(): void
    {
        $salary = $this->entry->employeePayrollProfile->salaryComponents()->firstOrFail();
        $salary->payrollComponent->update(['type' => 'deduction']);
        $this->run->update(['status' => 'revision_requested']);
        $this->post(route('hrd.periode-gaji.hitung', $this->run->period))->assertSessionHasErrors('period');
        $this->assertSame(1, PayrollRun::count());
        $this->assertSame('3000000.01', $this->run->fresh()->gross_total);
    }
}
