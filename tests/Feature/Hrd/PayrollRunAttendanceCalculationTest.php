<?php

namespace Tests\Feature\Hrd;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\CompanyCalendarDay;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
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

    private function setAttendanceRates(int $companyId, string $late, string $absence, string $reward, string $allowance = '0', string $transport = '0'): PayrollPolicy
    {
        return PayrollPolicy::updateOrCreate(
            ['company_id' => $companyId, 'code' => 'REGULAR', 'version' => 1],
            [
                'effective_from' => '2026-09-01',
                'rules' => ['attendance' => [
                    'late_deduction_per_minute' => $late,
                    'absence_deduction_per_day' => $absence,
                    'perfect_attendance_reward' => $reward,
                    'allowance_per_day' => $allowance,
                    'transport_per_day' => $transport,
                ]],
            ],
        );
    }

    public function test_late_minutes_are_deducted_at_the_configured_rate(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '1000', '0', '0');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:10:00', 'clock_in_status' => 'telat', 'clock_in_late_minutes' => 10,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
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
        $this->assertSame('attendance', $line->source);
        $this->assertEqualsWithDelta(10_000.0, (float) $line->amount, 0.001);
    }

    public function test_a_hand_confirmed_absence_is_deducted_using_the_base_salary_over_workdays_formula(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        // 2026-09-28 (present) and 2026-09-29 (no attendance, no approved
        // leave — the generator marks it absent on its own) are both
        // workdays, so Potongan Alpha = 3.000.000 ÷ 2 hari kerja × 1 hari
        // alpha = Rp1.500.000.
        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);
        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-29',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(1_500_000.0, (float) $entry->deduction_amount, 0.001);
        $this->assertSame(0, $entry->attendance_snapshot['unresolved_days']);

        $line = $entry->lines()->where('line_key', 'attendance-absence')->firstOrFail();
        $this->assertEqualsWithDelta(1_500_000.0, (float) $line->amount, 0.001);
        $this->assertSame('1500000.0000', $line->rate);
        $this->assertSame('1.0000', $line->quantity);
        $this->assertSame('3000000.00', $line->calculation_snapshot['base_salary']);
        $this->assertSame(2, $line->calculation_snapshot['work_days']);

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($hrd)
            ->get(route('hrd.hasil-payroll.show', $run))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('entries.0.lines.0.alpha_breakdown.base_salary', '3000000.00')
                ->where('entries.0.lines.0.alpha_breakdown.work_days', 2)
                ->where('entries.0.lines.0.alpha_breakdown.absent_days', 1)
                ->where('entries.0.lines.0.alpha_breakdown.capped_to_avoid_negative_net', false)
            );
    }

    public function test_an_employee_without_a_gaji_pokok_component_gets_no_alpha_deduction(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $allowance = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ALLOW', 'name' => 'Tunjangan',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$allowance, '3000000']]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('0.00', $entry->deduction_amount);
        $this->assertNull($entry->lines()->where('line_key', 'attendance-absence')->first());
    }

    public function test_a_manual_absence_correction_on_a_holiday_does_not_create_an_alpha_deduction(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '150000', '0');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);
        CompanyCalendarDay::create([
            'company_id' => $hrd->company_id,
            'date' => '2026-09-28',
            'type' => 'holiday',
            'description' => 'Libur perusahaan',
        ]);
        AttendanceReview::create([
            'user_id' => $employee->id,
            'company_id' => $hrd->company_id,
            'date' => '2026-09-28',
            'status' => 'validated',
            'classification' => 'absent',
            'correction_reason' => 'Koreksi lama sebelum kalender diperbarui.',
            'source_snapshot' => [],
            'schedule_snapshot' => [],
            'validated_by' => $hrd->id,
            'validated_at' => now(),
        ]);
        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionDoesntHaveErrors();

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(0, $entry->attendance_snapshot['absent_days']);
        $this->assertSame('holiday', $entry->attendance_snapshot['days'][0]['calendar_day_type']);
        $this->assertSame('0.00', $entry->deduction_amount);
        $this->assertNull($entry->lines()->where('line_key', 'attendance-absence')->first());
    }

    public function test_perfect_attendance_across_the_whole_window_earns_the_reward(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '100000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
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
        $this->assertSame('reward', $line->source);
        $this->assertSame('earning', $line->type);
    }

    public function test_a_no_show_day_never_pays_the_reward(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '100000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        // Deliberately not named "Gaji Pokok" — this test is about the
        // perfect-attendance reward, not the Potongan Alpha formula, so no
        // alpha deduction should be triggered by this component at all.
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        // 2026-09-28 is a full on-time day; 2026-09-29 has no attendance and
        // no approved leave, so the generator marks it absent on its own —
        // still breaks perfect attendance even though no alpha deduction
        // line exists here (see the deduction-formula tests separately).
        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
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

    public function test_attendance_allowance_is_paid_per_full_day_present_at_the_divisions_daily_rate(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '0', allowance: '50000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period))->assertSessionDoesntHaveErrors();

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $line = $entry->lines()->where('line_key', 'attendance-allowance')->firstOrFail();
        $this->assertSame('allowance', $line->source);
        $this->assertSame('earning', $line->type);
        $this->assertEqualsWithDelta(50_000.0, (float) $line->amount, 0.001);
        $this->assertEqualsWithDelta(3_050_000.0, (float) $entry->gross_amount, 0.001);
    }

    public function test_a_user_marked_not_allowance_eligible_gets_no_allowance_or_transport_but_keeps_base_salary(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '0', allowance: '50000', transport: '20000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active',
            'is_allowance_eligible' => false,
        ]);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period))->assertSessionDoesntHaveErrors();

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(0, $entry->lines()->where('line_key', 'attendance-allowance')->count());
        $this->assertSame(0, $entry->lines()->where('line_key', 'attendance-transport')->count());
        // Gaji Pokok from the profile still applies — only the two
        // attendance-based allowances are withheld.
        $this->assertEqualsWithDelta(3_000_000.0, (float) $entry->gross_amount, 0.001);
    }

    public function test_attendance_allowance_pays_half_rate_for_a_half_day(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '1000', '0', '0', allowance: '50000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        // A hand-corrected half_day — the default work schedule's 8-hour
        // shift isn't under test here, only the allowance payout rule.
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'half_day', 'late_minutes' => 10,
            'correction_reason' => 'Pulang lebih awal karena urusan keluarga, dikonfirmasi HRD.',
            'source_snapshot' => [], 'schedule_snapshot' => [], 'version' => 1,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('10000.00', $entry->deduction_amount);
        $this->assertSame(10, $entry->attendance_snapshot['late_minutes_total']);
        $line = $entry->lines()->where('line_key', 'attendance-allowance')->firstOrFail();
        $this->assertEqualsWithDelta(25_000.0, (float) $line->amount, 0.001);
        $this->assertFalse($entry->attendance_snapshot['perfect_attendance'], 'A half day must not count as perfect attendance.');
    }

    public function test_transport_allowance_uses_validated_present_and_half_day_counts(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '0', transport: '12000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $presentEmployee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $halfDayEmployee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($presentEmployee, $division, [[$base, '3000000']]);
        $this->makeProfile($halfDayEmployee, $division, [[$base, '3000000']]);
        foreach ([[$presentEmployee, 'present'], [$halfDayEmployee, 'half_day']] as [$employee, $classification]) {
            AttendanceReview::create([
                'user_id' => $employee->id,
                'company_id' => $hrd->company_id,
                'date' => '2026-09-28',
                'status' => 'validated',
                'classification' => $classification,
                'correction_reason' => 'Dikonfirmasi HRD untuk tes komponen otomatis.',
                'source_snapshot' => [],
                'schedule_snapshot' => [],
                'validated_by' => $hrd->id,
                'validated_at' => now(),
            ]);
        }
        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id,
            'name' => 'September 2026',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28',
            'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionDoesntHaveErrors();

        $presentEntry = PayrollEntry::where('user_id', $presentEmployee->id)->firstOrFail();
        $presentLine = $presentEntry->lines()->where('line_key', 'attendance-transport')->firstOrFail();
        $this->assertSame('earning', $presentLine->type);
        $this->assertSame('12000.00', $presentLine->amount);
        $this->assertSame('1.0000', $presentLine->quantity);
        $this->assertSame('3012000.00', $presentEntry->gross_amount);

        $halfDayEntry = PayrollEntry::where('user_id', $halfDayEmployee->id)->firstOrFail();
        $halfDayLine = $halfDayEntry->lines()->where('line_key', 'attendance-transport')->firstOrFail();
        $this->assertSame('6000.00', $halfDayLine->amount);
        $this->assertSame('0.5000', $halfDayLine->quantity);
        $this->assertSame('3006000.00', $halfDayEntry->gross_amount);
    }

    public function test_a_zero_allowance_rate_never_adds_an_allowance_line(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        // Deliberately not setting the allowance_per_day rate — it defaults
        // to 0, which must mean "no automatic allowance line".
        $this->setAttendanceRates($hrd->company_id, '0', '0', '0');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);

        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertNull($entry->lines()->where('line_key', 'attendance-allowance')->first());
        $this->assertEqualsWithDelta(3_000_000.0, (float) $entry->gross_amount, 0.001);
    }

    public function test_run_totals_aggregate_deduction_and_reward_across_employees(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '1000', '0', '100000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
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
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertEqualsWithDelta(15_000.0, (float) $run->deduction_total, 0.001);
        $this->assertEqualsWithDelta(100_000.0, (float) $run->reward_total, 0.001);
        $this->assertEqualsWithDelta(6_085_000.0, (float) $run->net_total, 0.001);
    }

    public function test_a_total_absence_month_caps_the_alpha_deduction_instead_of_going_net_negative(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $loan = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'LOAN', 'name' => 'Cicilan', 'type' => 'deduction',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '1000000'], [$loan, '50000']]);

        // No attendance at all across the 12 workdays in the window
        // (5-17 Oct 2026, one Sunday) — the generator marks every one
        // absent. Gaji Pokok ÷ 12 × 12 hari alpha = Rp1.000.000 exactly
        // matches gross, but the Rp50.000 cicilan on top would push net
        // negative, so the alpha portion must be capped at Rp950.000
        // (gross minus the manual deduction) instead.
        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-10-05', 'attendance_ends_on' => '2026-10-17',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionDoesntHaveErrors();

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('0.00', $entry->net_amount);
        $this->assertSame('1000000.00', $entry->deduction_amount);

        $line = $entry->lines()->where('line_key', 'attendance-absence')->firstOrFail();
        $this->assertSame('950000.00', $line->amount);
        $this->assertTrue($line->calculation_snapshot['capped_to_avoid_negative_net']);
    }

    public function test_an_approved_izin_terlambat_excuses_the_late_deduction_but_keeps_tunjangan(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '1000', '0', '0', allowance: '10000', transport: '10000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '3000000']]);
        LeaveRequest::create([
            'request_number' => 'REQ-PAYROLL-1', 'user_id' => $employee->id, 'type' => 'late',
            'leave_date' => '2026-09-28', 'status' => 'approved',
        ]);
        Attendance::create([
            'user_id' => $employee->id, 'tanggal' => '2026-09-28',
            'clock_in_time' => '2026-09-28 09:00:00', 'clock_in_status' => 'telat', 'clock_in_late_minutes' => 60,
            'clock_out_time' => '2026-09-28 17:00:00', 'clock_out_status' => 'tepat_waktu', 'clock_out_early_minutes' => 0,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period))->assertSessionDoesntHaveErrors();

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        // Approved Izin Terlambat waives Potongan Keterlambatan entirely...
        $this->assertNull($entry->lines()->where('line_key', 'attendance-late')->first());
        // ...but the employee still clocked in, so Tunjangan Kehadiran and
        // Transport are unaffected — this type never overrides attendance.
        $this->assertNotNull($entry->lines()->where('line_key', 'attendance-allowance')->first());
        $this->assertNotNull($entry->lines()->where('line_key', 'attendance-transport')->first());
        $this->assertSame('0.00', $entry->deduction_amount);
    }

    public function test_a_multi_day_sick_leave_skips_alpha_for_every_day_without_paying_tunjangan(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->setAttendanceRates($hrd->company_id, '0', '0', '0', allowance: '10000', transport: '10000');
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '1200000']]);
        // 3-day Sakit covering the whole window (Mon-Wed, 2026-09-28 to
        // 2026-09-30) — none of it should be alpha, and none of it earns
        // the attendance-based tunjangan either, since the employee never
        // actually comes in.
        LeaveRequest::create([
            'request_number' => 'REQ-PAYROLL-2', 'user_id' => $employee->id, 'type' => 'sick',
            'leave_date' => '2026-09-28', 'duration' => '3', 'status' => 'approved',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-30',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period))->assertSessionDoesntHaveErrors();

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('0.00', $entry->deduction_amount);
        $this->assertNull($entry->lines()->where('line_key', 'attendance-absence')->first());
        $this->assertNull($entry->lines()->where('line_key', 'attendance-allowance')->first());
        $this->assertNull($entry->lines()->where('line_key', 'attendance-transport')->first());
        $this->assertCount(3, $entry->attendance_snapshot['days']);
        foreach ($entry->attendance_snapshot['days'] as $day) {
            $this->assertSame('sick', $day['classification']);
        }
    }
}
