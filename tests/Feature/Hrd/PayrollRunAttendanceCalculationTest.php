<?php

namespace Tests\Feature\Hrd;

use App\Models\Attendance;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRunAttendanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(User $user, Division $division, array $components): EmployeePayrollProfile
    {
        $profile = EmployeePayrollProfile::create([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'division_id' => $division->id, 'position_id' => $user->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);

        foreach ($components as [$component, $amount]) {
            EmployeeSalaryComponent::create([
                'employee_payroll_profile_id' => $profile->id,
                'payroll_component_id' => $component->id,
                'amount' => $amount, 'basis' => 'monthly',
            ]);
        }

        return $profile;
    }

    private function setAttendanceRates(int $companyId, string $late, string $absence, string $reward): PayrollPolicy
    {
        return PayrollPolicy::updateOrCreate(
            ['company_id' => $companyId, 'code' => 'REGULAR', 'version' => 1],
            [
                'effective_from' => '2026-09-01',
                'rules' => ['attendance' => [
                    'late_deduction_per_minute' => $late,
                    'absence_deduction_per_day' => $absence,
                    'perfect_attendance_reward' => $reward,
                ]],
            ],
        );
    }

    public function test_late_minutes_are_deducted_at_the_configured_rate(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '1000', '0', '0');
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:10:00', 'clock_in_status' => 'telat', 'clock_in_late_minutes' => 10,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionDoesntHaveErrors();

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(10_000.0, (float) $entry->deduction_amount, 0.001);
        $this->assertEqualsWithDelta(3_000_000.0, (float) $entry->gross_amount, 0.001);
        $this->assertEqualsWithDelta(2_990_000.0, (float) $entry->net_amount, 0.001);

        $line = $entry->lines()->where('line_key', 'attendance-late')->firstOrFail();
        $this->assertSame('attendance_deduction', $line->category);
        $this->assertSame('attendance', $line->source);
        $this->assertEqualsWithDelta(10_000.0, (float) $line->amount, 0.001);
    }

    public function test_a_hand_confirmed_absence_is_deducted_at_the_configured_daily_rate(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '150000', '0');
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        // No attendance and no approved leave on a workday — the generator
        // now marks this absent on its own, no HRD correction required.
        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(150_000.0, (float) $entry->deduction_amount, 0.001);
        $this->assertSame(0, $entry->attendance_snapshot['unresolved_days']);

        $line = $entry->lines()->where('line_key', 'attendance-absence')->firstOrFail();
        $this->assertEqualsWithDelta(150_000.0, (float) $line->amount, 0.001);
    }

    public function test_perfect_attendance_across_the_whole_window_earns_the_reward(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '100000');
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(100_000.0, (float) $entry->reward_amount, 0.001);
        $this->assertEqualsWithDelta(3_100_000.0, (float) $entry->gross_amount, 0.001);
        $this->assertEqualsWithDelta(3_100_000.0, (float) $entry->net_amount, 0.001);
        $this->assertTrue($entry->attendance_snapshot['perfect_attendance']);

        $line = $entry->lines()->where('line_key', 'attendance-perfect-reward')->firstOrFail();
        $this->assertSame('attendance_reward', $line->category);
        $this->assertSame('reward', $line->source);
        $this->assertSame('earning', $line->type);
    }

    public function test_a_no_show_day_never_pays_the_reward_even_with_a_zero_absence_rate(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '100000');
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        // 2026-09-28 is a full on-time day; 2026-09-29 has no attendance and
        // no approved leave, so the generator marks it absent on its own —
        // the absence rate here is 0, so it costs nothing, but it still
        // breaks perfect attendance.
        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-29',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('0.00', $entry->reward_amount);
        $this->assertSame('0.00', $entry->deduction_amount);
        $this->assertFalse($entry->attendance_snapshot['perfect_attendance']);
        $this->assertSame(0, $entry->attendance_snapshot['unresolved_days']);
        $this->assertSame(1, $entry->attendance_snapshot['absent_days']);
        $this->assertNull($entry->lines()->where('line_key', 'attendance-perfect-reward')->first());
    }

    public function test_run_totals_aggregate_deduction_and_reward_across_employees(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '1000', '0', '100000');
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);

        $late = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($late, $division, [[$base, '3000000']]);
        Attendance::create([
            'user_id' => $late->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:15:00', 'clock_in_status' => 'telat', 'clock_in_late_minutes' => 15,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $onTime = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($onTime, $division, [[$base, '3000000']]);
        Attendance::create([
            'user_id' => $onTime->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertEqualsWithDelta(15_000.0, (float) $run->deduction_total, 0.001);
        $this->assertEqualsWithDelta(100_000.0, (float) $run->reward_total, 0.001);
        $this->assertEqualsWithDelta(6_085_000.0, (float) $run->net_total, 0.001);
    }
}
