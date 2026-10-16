<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\PayrollComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $components): array
    {
        return [
            'components' => $components,
        ];
    }

    public function test_hrd_can_set_a_payroll_profile_with_salary_components_for_the_first_time(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);
        $allowance = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ALLOW', 'name' => 'Tunjangan',
            'type' => 'earning', ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload([
                ['payroll_component_id' => $base->id, 'amount' => '2500000'],
                ['payroll_component_id' => $allowance->id, 'amount' => '300000'],
            ]))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('hrd.profil-gaji.index'));

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('permanent', $employee->fresh()->employment_type);
        // division_id now always comes from the employee's own record (set
        // at Manajemen User), never from the form.
        $this->assertSame($employee->division_id, $profile->division_id);
        $this->assertSame($employee->position_id, $profile->position_id);
        $this->assertCount(2, $profile->salaryComponents);
        $this->assertEqualsWithDelta(2_800_000.0, (float) $profile->salaryComponents->sum('amount'), 0.001);
    }

    public function test_hrd_can_set_a_payroll_profile_for_an_employee_without_a_position(): void
    {
        // Manajemen User no longer sets position_id at all (Division
        // replaced Position) — every employee created through it has a
        // null position_id, so saving their profile must not require one.
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'position_id' => null,
        ]);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE5', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload([
                ['payroll_component_id' => $base->id, 'amount' => '2500000'],
            ]))
            ->assertSessionDoesntHaveErrors();

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertNull($profile->position_id);
    }

    public function test_updating_a_profile_removes_components_no_longer_submitted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);
        $allowance = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ALLOW', 'name' => 'Tunjangan',
            'type' => 'earning', ]);

        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), $this->payload([
            ['payroll_component_id' => $base->id, 'amount' => '2500000'],
            ['payroll_component_id' => $allowance->id, 'amount' => '300000'],
        ]));

        // Second save drops the allowance line entirely.
        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), $this->payload([
            ['payroll_component_id' => $base->id, 'amount' => '2600000'],
        ]));

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertCount(1, $profile->fresh()->salaryComponents);
        $this->assertSame(1, EmployeePayrollProfile::where('user_id', $employee->id)->count(), 'Must update the one profile row, not create a second.');
    }

    public function test_a_component_from_another_company_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $otherCompany = Company::factory()->create();
        $foreignComponent = PayrollComponent::create([
            'company_id' => $otherCompany->id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload([
                ['payroll_component_id' => $foreignComponent->id, 'amount' => '2500000'],
            ]))
            ->assertSessionHasErrors('components');
    }

    public function test_a_non_karyawan_user_cannot_be_targeted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd', 'company_id' => $hrd->company_id]);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);

        // A fully valid payload — this must 404 from the role check inside
        // the controller, not from validation rejecting an empty payload.
        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $otherHrd), $this->payload([
                ['payroll_component_id' => $base->id, 'amount' => '2500000'],
            ]))
            ->assertNotFound();
    }

    public function test_hrd_cannot_view_or_edit_another_companys_employee_profile(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $otherEmployee = User::factory()->create(['company_id' => $otherHrd->company_id, 'role' => 'karyawan']);
        $otherComponent = PayrollComponent::create([
            'company_id' => $otherHrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);

        $this->actingAs($hrd)
            ->get(route('hrd.profil-gaji.edit', $otherEmployee))
            ->assertForbidden();

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $otherEmployee), $this->payload([
                ['payroll_component_id' => $otherComponent->id, 'amount' => '2500000'],
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('employee_payroll_profiles', ['user_id' => $otherEmployee->id]);
    }

    public function test_hrd_only_sees_their_own_companys_employees(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        User::factory()->create(['company_id' => $otherHrd->company_id, 'role' => 'karyawan', 'name' => 'Karyawan Perusahaan Lain']);

        $response = $this->actingAs($hrd)->get(route('hrd.profil-gaji.index'))->assertOk();

        $names = collect($response->viewData('page')['props']['employees'])->pluck('name');
        $this->assertFalse($names->contains('Karyawan Perusahaan Lain'));
    }

    public function test_karyawan_cannot_access_payroll_profile_management(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.profil-gaji.index'))
            ->assertForbidden();
    }

    public function test_editing_the_profile_is_blocked_until_employment_started_on_is_set_on_the_user(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'employment_started_on' => null,
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.profil-gaji.edit', $employee))
            ->assertRedirect(route('hrd.profil-gaji.index'));
    }

    public function test_editing_the_profile_is_blocked_until_division_is_set_on_the_user(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'division_id' => null,
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.profil-gaji.edit', $employee))
            ->assertRedirect(route('hrd.profil-gaji.index'));
    }

    public function test_editing_the_profile_is_blocked_until_employment_type_is_set_on_the_user(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'employment_type' => null,
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.profil-gaji.edit', $employee))
            ->assertRedirect(route('hrd.profil-gaji.index'));
    }

    public function test_system_managed_earning_components_are_hidden_from_the_dropdown(): void
    {
        // Reward Kehadiran Sempurna and Lembur are earning-type but
        // computed automatically by the payroll run from attendance/
        // overtime data — offering them here would let HRD set a fixed
        // amount that then gets paid on top of the calculated one.
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ATTENDANCE_PERFECT', 'name' => 'Reward Kehadiran Sempurna',
            'type' => 'earning', ]);
        PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'OVERTIME', 'name' => 'Lembur',
            'type' => 'earning', ]);
        $allowance = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'FIXED_ALLOWANCE', 'name' => 'Tunjangan Tetap',
            'type' => 'earning', ]);

        $response = $this->actingAs($hrd)->get(route('hrd.profil-gaji.edit', $employee))->assertOk();

        $names = collect($response->viewData('page')['props']['availableEarningComponents'])->pluck('name');
        $this->assertFalse($names->contains('Reward Kehadiran Sempurna'));
        $this->assertFalse($names->contains('Lembur'));
        $this->assertTrue($names->contains('Tunjangan Tetap'));
        $this->assertNotNull($allowance);
    }

    public function test_hrd_can_set_a_manual_deduction_component_alongside_earning_components(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE4', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);
        $loan = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'KASBON', 'name' => 'Potongan Kasbon',
            'type' => 'deduction', ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload([
                ['payroll_component_id' => $base->id, 'amount' => '2500000'],
                ['payroll_component_id' => $loan->id, 'amount' => '200000'],
            ]))
            ->assertSessionDoesntHaveErrors();

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertCount(2, $profile->salaryComponents);

        $response = $this->actingAs($hrd)->get(route('hrd.profil-gaji.edit', $employee))->assertOk();
        $profileProp = $response->viewData('page')['props']['profile'];

        $earning = collect($profileProp['earningComponents']);
        $this->assertCount(1, $earning);
        $this->assertSame($base->id, $earning->first()['payroll_component_id']);
        $this->assertSame('2500000.00', $earning->first()['amount']);

        $deduction = collect($profileProp['deductionComponents']);
        $this->assertCount(1, $deduction);
        $this->assertSame($loan->id, $deduction->first()['payroll_component_id']);
        $this->assertSame('200000.00', $deduction->first()['amount']);
    }

    public function test_system_managed_deduction_components_are_hidden_from_the_dropdown(): void
    {
        // Potongan Alpha and Potongan Keterlambatan are computed
        // automatically by the payroll run from attendance data — offering
        // them here would let HRD set a fixed amount on top of the
        // calculated one.
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ATTENDANCE_ABSENCE', 'name' => 'Potongan Alpha',
            'type' => 'deduction', ]);
        PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ATTENDANCE_LATE', 'name' => 'Potongan Keterlambatan',
            'type' => 'deduction', ]);
        $loan = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'KASBON', 'name' => 'Potongan Kasbon',
            'type' => 'deduction', ]);

        $response = $this->actingAs($hrd)->get(route('hrd.profil-gaji.edit', $employee))->assertOk();

        $names = collect($response->viewData('page')['props']['availableDeductionComponents'])->pluck('name');
        $this->assertFalse($names->contains('Potongan Alpha'));
        $this->assertFalse($names->contains('Potongan Keterlambatan'));
        $this->assertTrue($names->contains('Potongan Kasbon'));
        $this->assertNotNull($loan);
    }

    public function test_setting_a_system_managed_deduction_component_is_rejected_even_via_a_crafted_request(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $alpha = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ATTENDANCE_ABSENCE', 'name' => 'Potongan Alpha',
            'type' => 'deduction', ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload([
                ['payroll_component_id' => $alpha->id, 'amount' => '100000'],
            ]))
            ->assertSessionHasErrors('components');

        $this->assertDatabaseMissing('employee_payroll_profiles', ['user_id' => $employee->id]);
    }

    public function test_setting_a_system_managed_component_is_rejected_even_via_a_crafted_request(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $reward = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ATTENDANCE_PERFECT', 'name' => 'Reward Kehadiran Sempurna',
            'type' => 'earning', ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload([
                ['payroll_component_id' => $reward->id, 'amount' => '500000'],
            ]))
            ->assertSessionHasErrors('components');

        $this->assertDatabaseMissing('employee_payroll_profiles', ['user_id' => $employee->id]);
    }

    public function test_profile_employment_started_on_division_and_employment_type_always_come_from_the_user_not_the_form(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan',
            'employment_started_on' => '2022-03-15', 'employment_type' => 'permanent',
        ]);
        $wrongDivision = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional 2']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE2', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);

        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), [
            // Deliberately submitting different values than the employee's
            // own record, to prove all three are ignored — this form no
            // longer accepts any of them.
            'division_id' => $wrongDivision->id,
            'effective_from' => '2026-10-01',
            'employment_started_on' => '1999-01-01',
            'employment_type' => 'contract',
            'components' => [['payroll_component_id' => $base->id, 'amount' => '2000000']],
        ])->assertSessionDoesntHaveErrors();

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('2022-03-15', $profile->employment_started_on->toDateString());
        $this->assertSame($employee->division_id, $profile->division_id);
        $this->assertNotSame($wrongDivision->id, $profile->division_id);
        $this->assertSame('permanent', $profile->employment_type);
    }

    public function test_effective_from_is_set_once_and_never_changes_on_later_edits(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'employment_started_on' => '2022-03-15',
        ]);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE3', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);

        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), $this->payload([
            ['payroll_component_id' => $base->id, 'amount' => '2000000'],
        ]))->assertSessionDoesntHaveErrors();

        $firstEffectiveFrom = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail()->effective_from->toDateString();
        $this->assertSame('2022-03-15', $firstEffectiveFrom, 'First save must default to the employee\'s employment_started_on.');

        // A later edit (e.g. a raise) must not shift when the current
        // profile started applying — even if a different date is crafted
        // into the request, this form no longer accepts it.
        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), array_merge($this->payload([
            ['payroll_component_id' => $base->id, 'amount' => '2500000'],
        ]), ['effective_from' => '2099-01-01']))->assertSessionDoesntHaveErrors();

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame($firstEffectiveFrom, $profile->effective_from->toDateString());
    }
}
