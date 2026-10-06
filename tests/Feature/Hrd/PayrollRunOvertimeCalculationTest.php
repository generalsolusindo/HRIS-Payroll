<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRunOvertimeCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(User $user, Division $division, PayrollComponent $component, string $amount): EmployeePayrollProfile
    {
        $profile = EmployeePayrollProfile::create([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'division_id' => $division->id, 'position_id' => $user->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $component->id,
            'amount' => $amount, 'basis' => 'monthly',
        ]);

        return $profile;
    }

    public function test_approved_overtime_within_the_attendance_window_is_paid_at_the_configured_hourly_rate(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        PayrollPolicy::create([
            'company_id' => $hrd->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-09-01',
            'rules' => ['overtime' => ['rate_per_hour' => '20000']],
        ]);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, $base, '3000000');

        OvertimeRequest::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 21:00:00',
            'break_minutes' => 0, 'reason' => 'Lembur', 'status' => 'approved', 'approved_minutes' => 180,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        // 180 minutes = 3 hours * Rp20.000 = Rp60.000.
        $this->assertEqualsWithDelta(3_060_000.0, (float) $entry->gross_amount, 0.001);
        $this->assertEqualsWithDelta(3_060_000.0, (float) $entry->net_amount, 0.001);

        $line = $entry->lines()->where('line_key', 'overtime')->firstOrFail();
        $this->assertSame('overtime', $line->category);
        $this->assertSame('overtime', $line->source);
        $this->assertSame('earning', $line->type);
        $this->assertEqualsWithDelta(60_000.0, (float) $line->amount, 0.001);
    }

    public function test_only_approved_overtime_counts_not_submitted_or_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        PayrollPolicy::create([
            'company_id' => $hrd->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-09-01',
            'rules' => ['overtime' => ['rate_per_hour' => '20000']],
        ]);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, $base, '3000000');

        OvertimeRequest::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 20:00:00',
            'reason' => 'Masih diajukan', 'status' => 'submitted',
        ]);
        OvertimeRequest::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'starts_at' => '2026-09-28 20:00:00', 'ends_at' => '2026-09-28 22:00:00',
            'reason' => 'Ditolak', 'status' => 'rejected',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(3_000_000.0, (float) $entry->gross_amount, 0.001);
        $this->assertNull($entry->lines()->where('line_key', 'overtime')->first());
    }

    public function test_a_zero_overtime_rate_never_creates_a_line_even_with_approved_overtime(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        // No policy created at all — defaults to rate 0 via firstOrCreate.
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, $base, '3000000');

        OvertimeRequest::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 21:00:00',
            'reason' => 'Lembur', 'status' => 'approved', 'approved_minutes' => 180,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(3_000_000.0, (float) $entry->gross_amount, 0.001);
        $this->assertNull($entry->lines()->where('line_key', 'overtime')->first());
    }
}
