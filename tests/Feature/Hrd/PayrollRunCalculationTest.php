<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollRunCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(User $user, Division $division, array $components): EmployeePayrollProfile
    {
        $profile = EmployeePayrollProfile::create([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'division_id' => $division->id, 'position_id' => $user->position_id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-01-01',
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

    public function test_calculating_a_period_creates_a_run_with_entries_and_lines_for_employees_with_profiles(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $allowance = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ALLOW', 'name' => 'Tunjangan',
            'type' => 'earning', 'category' => 'allowance',
        ]);

        $budi = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active', 'name' => 'Budi']);
        $this->makeProfile($budi, $division, [[$base, '2500000'], [$allowance, '300000']]);

        // No profile at all — must be skipped, not zeroed.
        User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-10', 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertSame('draft', $run->status);
        $this->assertSame(1, $run->version);
        $this->assertSame(1, $run->employee_count, 'Only the employee with a profile counts.');
        $this->assertEqualsWithDelta(2_800_000.0, (float) $run->gross_total, 0.001);
        $this->assertEqualsWithDelta(2_800_000.0, (float) $run->net_total, 0.001);

        $entry = PayrollEntry::where('payroll_run_id', $run->id)->where('user_id', $budi->id)->firstOrFail();
        $this->assertSame('Operasional', $entry->division_name);
        $this->assertEqualsWithDelta(2_800_000.0, (float) $entry->gross_amount, 0.001);
        $this->assertEqualsWithDelta(2_800_000.0, (float) $entry->net_amount, 0.001);
        $this->assertCount(2, $entry->lines);
    }

    public function test_an_employee_without_any_salary_components_is_skipped(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        // Profile exists but has zero salary components attached.
        $this->makeProfile($employee, $division, []);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-10', 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertSame(0, $run->employee_count);
        $this->assertSame(0, PayrollEntry::where('payroll_run_id', $run->id)->count());
    }

    public function test_a_period_cannot_be_calculated_twice(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-10', 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionHasErrors('period');

        $this->assertSame(1, PayrollRun::where('payroll_period_id', $period->id)->count());
    }

    public function test_payment_destination_is_populated_from_the_profiles_bank_details_and_encrypted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $profile = $this->makeProfile($employee, $division, [[$base, '2500000']]);
        $profile->update(['bank_name' => 'BCA', 'bank_account_number' => '111222333', 'bank_account_holder' => $employee->name]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-10', 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $entry = PayrollEntry::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('111222333', $entry->payment_destination['account_number']);

        $raw = DB::table('payroll_entries')->where('id', $entry->id)->value('payment_destination');
        $this->assertStringNotContainsString('111222333', (string) $raw);
    }

    public function test_the_run_result_page_shows_entries_grouped_for_drill_down(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $this->makeProfile($employee, $division, [[$base, '2500000']]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-10', 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));
        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();

        $this->actingAs($hrd)
            ->get(route('hrd.hasil-payroll.show', $run))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/payroll-run-show')
                ->where('run.employee_count', 1)
                ->has('entries', 1)
                ->where('entries.0.division_name', 'Operasional')
            );
    }

    public function test_karyawan_cannot_calculate_or_view_payroll_runs(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $period = PayrollPeriod::create([
            'company_id' => $karyawan->company_id, 'code' => '2026-10', 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);

        $this->actingAs($karyawan)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertForbidden();
    }
}
