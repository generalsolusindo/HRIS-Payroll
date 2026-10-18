<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\PayrollAuditLog;
use App\Models\PayrollComponent;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_payroll_profile_writes_an_audit_log_entry_without_bank_details(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);

        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), [
            'division_id' => $division->id,
            'effective_from' => '2026-10-01',
            'employment_started_on' => '2026-09-01',
            'employment_type' => 'permanent',
            'bank_name' => 'BCA',
            'bank_account_number' => '111222333',
            'bank_account_holder' => $employee->name,
            'components' => [['payroll_component_id' => $base->id, 'amount' => '3000000']],
            'reason' => 'Setup gaji awal.',
        ]);

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $log = PayrollAuditLog::where('entity_type', 'EmployeePayrollProfile')->where('entity_id', $profile->id)->firstOrFail();

        $this->assertSame('created', $log->action);
        $this->assertSame('Setup gaji awal.', $log->reason);
        $this->assertNull($log->before_values);
        $this->assertSame('3000000', $log->after_values['components'][0]['amount']);
        $this->assertArrayNotHasKey('bank_account_number', $log->after_values);
        $this->assertArrayNotHasKey('bank_account_holder', $log->after_values);

        $raw = $log->getAttributes()['after_values'];
        $this->assertStringNotContainsString('111222333', (string) $raw);
    }

    public function test_updating_an_existing_profile_logs_the_before_state_and_defaults_the_reason(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        $profile->salaryComponents()->create(['payroll_component_id' => $base->id, 'amount' => '2500000', 'basis' => 'monthly']);

        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), [
            'division_id' => $division->id,
            'effective_from' => '2026-09-01',
            'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
            'components' => [['payroll_component_id' => $base->id, 'amount' => '3000000']],
        ]);

        $log = PayrollAuditLog::where('entity_type', 'EmployeePayrollProfile')->where('entity_id', $profile->id)->firstOrFail();
        $this->assertSame('updated', $log->action);
        $this->assertSame('2500000.00', $log->before_values['components'][0]['amount']);
        $this->assertSame('3000000', $log->after_values['components'][0]['amount']);
        $this->assertStringContainsString('diperbarui oleh HRD', $log->reason);
    }

    public function test_updating_the_attendance_policy_writes_an_audit_log_entry(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $policy = PayrollPolicy::create([
            'company_id' => $hrd->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => [],
        ]);

        $this->actingAs($hrd)->put(route('hrd.kebijakan-absensi.update', $policy), [
            'late_deduction_per_minute' => '2000',
            'absence_deduction_per_day' => '150000',
            'perfect_attendance_reward' => '100000',
            'allowance_per_day' => '50000',
            'transport_per_day' => '12000',
            'overtime_rate_per_hour' => '25000',
        ]);

        $log = PayrollAuditLog::where('entity_type', 'PayrollPolicy')->where('entity_id', $policy->id)->firstOrFail();
        $this->assertSame('updated', $log->action);
        $this->assertSame([], $log->before_values);
        $this->assertSame('2000.00', $log->after_values['attendance']['late_deduction_per_minute']);
        $this->assertSame('25000.00', $log->after_values['overtime']['rate_per_hour']);
        $this->assertStringContainsString('diperbarui oleh HRD', $log->reason);
    }

    public function test_hrd_can_view_the_audit_log_filtered_by_entity_type(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $other = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'hrd']);

        PayrollAuditLog::create([
            'company_id' => $hrd->company_id, 'actor_id' => $other->id, 'actor_snapshot' => ['name' => $other->name],
            'entity_type' => 'AttendanceReview', 'entity_id' => 1, 'action' => 'corrected',
            'before_values' => ['classification' => 'unresolved'], 'after_values' => ['classification' => 'sick'],
            'reason' => 'Test',
        ]);
        PayrollAuditLog::create([
            'company_id' => $hrd->company_id, 'actor_id' => $other->id, 'actor_snapshot' => ['name' => $other->name],
            'entity_type' => 'PayrollPolicy', 'entity_id' => 1, 'action' => 'updated',
            'before_values' => [], 'after_values' => ['attendance' => []],
            'reason' => 'Test policy',
        ]);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.audit-log.index', ['entity_type' => 'AttendanceReview']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/audit-log'));

        $rows = collect($response->viewData('page')['props']['logs']['data']);
        $this->assertTrue($rows->every(fn ($row) => $row['entity_type'] === 'AttendanceReview'));
    }

    public function test_karyawan_and_manager_cannot_access_the_audit_log(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($karyawan)->get(route('hrd.audit-log.index'))->assertForbidden();

        $manager = User::factory()->create(['role' => 'admin']);
        $this->actingAs($manager)->get(route('hrd.audit-log.index'))->assertForbidden();
    }
}
