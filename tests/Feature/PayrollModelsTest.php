<?php

namespace Tests\Feature;

use App\Models\AttendanceReview;
use App\Models\CompanyCalendarDay;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\PayrollApproval;
use App\Models\PayrollAuditLog;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPayment;
use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Exercises the Eloquent layer on top of the 16 payroll tables end-to-end
 * through real model operations (relations, casts, encryption) rather than
 * raw DB::table() calls — PayrollSchemaTest.php already covers the
 * database-level constraints directly.
 */
class PayrollModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_payroll_chain_can_be_built_and_read_back_through_eloquent(): void
    {
        $user = User::factory()->create([
            'bank_name' => 'BCA', 'bank_account_number' => '1234567890', 'bank_account_holder' => 'Pemilik Rekening',
        ]);
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['role' => 'admin']);
        $finance = User::factory()->create(['role' => 'finance']);

        $division = Division::create([
            'company_id' => $user->company_id,
            'name' => 'Operasional', 'is_active' => true,
        ]);
        $this->assertTrue($division->company->is($user->company));

        $baseSalary = PayrollComponent::create([
            'company_id' => $user->company_id,
            'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning', ]);
        $allowance = PayrollComponent::create([
            'company_id' => $user->company_id,
            'code' => 'ALLOW', 'name' => 'Tunjangan', 'type' => 'earning', ]);

        $policy = PayrollPolicy::create([
            'company_id' => $user->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => ['late_deduction_per_minute' => 0],
            'created_by' => $hrd->id,
        ]);
        $this->assertSame(['late_deduction_per_minute' => 0], $policy->fresh()->rules);

        $profile = EmployeePayrollProfile::create([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'division_id' => $division->id, 'position_id' => $user->position_id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-10-01',
            'employment_type' => 'permanent',
            'created_by' => $hrd->id,
        ]);

        // Bank account number lives on the user account and must be
        // encrypted at rest — same expectation as User::face_descriptor.
        $rawStoredValue = DB::table('users')
            ->where('id', $user->id)->value('bank_account_number');
        $this->assertNotSame('1234567890', $rawStoredValue);
        $this->assertSame('1234567890', $user->fresh()->bank_account_number);

        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id,
            'payroll_component_id' => $baseSalary->id,
            'amount' => '2500000.00', 'basis' => 'monthly',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id,
            'payroll_component_id' => $allowance->id,
            'amount' => '300000.00', 'basis' => 'monthly',
        ]);
        $this->assertCount(2, $profile->salaryComponents);
        $this->assertSame('2500000.00', (string) $profile->salaryComponents()
            ->where('payroll_component_id', $baseSalary->id)->first()->amount);

        $period = PayrollPeriod::create([
            'company_id' => $user->company_id, 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);

        $run = PayrollRun::create([
            'payroll_period_id' => $period->id, 'version' => 1, 'payroll_policy_id' => $policy->id,
            'policy_snapshot' => ['code' => 'REGULAR', 'version' => 1],
            'company_snapshot' => ['name' => $user->company->name],
            'status' => 'draft', 'created_by' => $hrd->id,
        ]);
        $this->assertTrue($run->period->is($period));
        $this->assertTrue($run->policy->is($policy));

        $entry = PayrollEntry::create([
            'payroll_run_id' => $run->id, 'user_id' => $user->id,
            'employee_payroll_profile_id' => $profile->id, 'division_id' => $division->id,
            'employee_snapshot' => ['name' => $user->name],
            'division_name' => $division->name, 'position_name' => $user->position->name,
            'attendance_snapshot' => [], 'compensation_snapshot' => ['basis' => 'monthly'],
            'payment_destination' => ['bank' => 'BCA', 'account' => '1234567890'],
            'gross_amount' => '2800000.00', 'deduction_amount' => '0.00',
            'net_amount' => '2800000.00',
        ]);

        $rawDestination = DB::table('payroll_entries')->where('id', $entry->id)->value('payment_destination');
        $this->assertStringNotContainsString('1234567890', (string) $rawDestination);
        $this->assertSame(['bank' => 'BCA', 'account' => '1234567890'], $entry->fresh()->payment_destination);

        PayrollEntryLine::create([
            'payroll_entry_id' => $entry->id, 'payroll_component_id' => $baseSalary->id,
            'line_key' => 'base-salary', 'component_code' => 'BASE', 'component_name' => 'Gaji Pokok',
            'type' => 'earning', 'quantity' => 1, 'rate' => '2500000.0000',
            'amount' => '2500000.00', 'source' => 'salary', 'calculation_snapshot' => ['basis' => 'monthly'],
        ]);
        PayrollEntryLine::create([
            'payroll_entry_id' => $entry->id, 'payroll_component_id' => $allowance->id,
            'line_key' => 'allowance', 'component_code' => 'ALLOW', 'component_name' => 'Tunjangan',
            'type' => 'earning', 'quantity' => 1, 'rate' => '300000.0000',
            'amount' => '300000.00', 'source' => 'salary', 'calculation_snapshot' => [],
        ]);

        $this->assertCount(2, $entry->fresh()->lines);
        $this->assertEqualsWithDelta(2_800_000.0, (float) $entry->lines()->sum('amount'), 0.001);

        // Append-only workflow rows — no updated_at column on either table.
        $approval = PayrollApproval::create([
            'payroll_run_id' => $run->id, 'action' => 'submitted',
            'from_status' => 'draft', 'to_status' => 'submitted',
            'actor_id' => $hrd->id, 'actor_snapshot' => ['name' => $hrd->name],
        ]);
        $this->assertNull($approval->getUpdatedAtColumn());
        $this->assertNotNull($approval->fresh()->created_at);

        $audit = PayrollAuditLog::create([
            'company_id' => $user->company_id, 'payroll_run_id' => $run->id,
            'actor_id' => $manager->id, 'actor_snapshot' => ['name' => $manager->name],
            'entity_type' => 'payroll_run', 'entity_id' => $run->id, 'action' => 'approved',
            'before_values' => ['status' => 'submitted'], 'after_values' => ['status' => 'approved'],
            'reason' => 'Disetujui setelah verifikasi.',
        ]);
        $this->assertNull($audit->getUpdatedAtColumn());
        $this->assertTrue($audit->run->is($run));

        $payment = PayrollPayment::create([
            'payroll_entry_id' => $entry->id,
            'idempotency_key' => (string) Str::uuid(),
            'amount' => '2800000.00', 'method' => 'bank_transfer', 'status' => 'pending',
            'destination_snapshot' => ['bank' => 'BCA', 'account' => '1234567890'],
            'recorded_by' => $finance->id,
        ]);
        $rawPaymentDestination = DB::table('payroll_payments')
            ->where('id', $payment->id)->value('destination_snapshot');
        $this->assertStringNotContainsString('1234567890', (string) $rawPaymentDestination);
        $this->assertSame(['bank' => 'BCA', 'account' => '1234567890'], $payment->fresh()->destination_snapshot);

        $payslip = PayrollPayslip::create([
            'payroll_entry_id' => $entry->id, 'number' => 'SLIP-2026-10-'.$user->id,
            'version' => 1, 'status' => 'draft', 'snapshot' => ['net_amount' => '2800000.00'],
        ]);
        $this->assertTrue($payslip->entry->is($entry));

        $calendarDay = CompanyCalendarDay::create([
            'company_id' => $user->company_id, 'date' => '2026-10-01', 'type' => 'workday',
        ]);
        $this->assertTrue($calendarDay->company->is($user->company));

        $review = AttendanceReview::create([
            'user_id' => $user->id, 'company_id' => $user->company_id, 'date' => '2026-10-01',
            'status' => 'draft', 'classification' => 'unresolved',
            'source_snapshot' => [], 'schedule_snapshot' => [],
        ]);
        $this->assertTrue($review->user->is($user));

        $overtime = OvertimeRequest::create([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'starts_at' => '2026-10-01 18:00:00', 'ends_at' => '2026-10-01 20:00:00',
            'reason' => 'Tutup buku bulanan.', 'status' => 'submitted',
        ]);
        $this->assertTrue($overtime->user->is($user));

        // Company/User-side relations resolve both directions.
        $this->assertTrue($user->company->divisions->contains($division));
        $this->assertTrue($user->company->payrollComponents->contains($baseSalary));
        $this->assertTrue($user->payrollProfiles->contains($profile));
        $this->assertTrue($user->attendanceReviews->contains($review));
        $this->assertTrue($user->overtimeRequests->contains($overtime));
    }
}
