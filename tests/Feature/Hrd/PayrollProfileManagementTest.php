<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\PayrollComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Division $division, array $components): array
    {
        return [
            'division_id' => $division->id,
            'effective_from' => '2026-10-01',
            'employment_started_on' => '2026-09-01',
            'employment_type' => 'permanent',
            'components' => $components,
        ];
    }

    public function test_hrd_can_set_a_payroll_profile_with_salary_components_for_the_first_time(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $allowance = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ALLOW', 'name' => 'Tunjangan',
            'type' => 'earning', 'category' => 'allowance',
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload($division, [
                ['payroll_component_id' => $base->id, 'amount' => '2500000'],
                ['payroll_component_id' => $allowance->id, 'amount' => '300000'],
            ]))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('hrd.profil-gaji.index'));

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame($division->id, $profile->division_id);
        $this->assertSame($employee->position_id, $profile->position_id);
        $this->assertCount(2, $profile->salaryComponents);
        $this->assertEqualsWithDelta(2_800_000.0, (float) $profile->salaryComponents->sum('amount'), 0.001);
    }

    public function test_updating_a_profile_removes_components_no_longer_submitted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $allowance = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'ALLOW', 'name' => 'Tunjangan',
            'type' => 'earning', 'category' => 'allowance',
        ]);

        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), $this->payload($division, [
            ['payroll_component_id' => $base->id, 'amount' => '2500000'],
            ['payroll_component_id' => $allowance->id, 'amount' => '300000'],
        ]));

        // Second save drops the allowance line entirely.
        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), $this->payload($division, [
            ['payroll_component_id' => $base->id, 'amount' => '2600000'],
        ]));

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertCount(1, $profile->fresh()->salaryComponents);
        $this->assertSame(1, EmployeePayrollProfile::where('user_id', $employee->id)->count(), 'Must update the one profile row, not create a second.');
    }

    public function test_bank_account_number_is_encrypted_at_rest(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);

        $payload = $this->payload($division, [['payroll_component_id' => $base->id, 'amount' => '2500000']]);
        $payload['bank_name'] = 'BCA';
        $payload['bank_account_number'] = '9876543210';
        $payload['bank_account_holder'] = $employee->name;

        $this->actingAs($hrd)->put(route('hrd.profil-gaji.update', $employee), $payload);

        $raw = DB::table('employee_payroll_profiles')
            ->where('user_id', $employee->id)->value('bank_account_number');
        $this->assertNotSame('9876543210', $raw);

        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('9876543210', $profile->bank_account_number);
    }

    public function test_a_division_from_another_company_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $otherCompany = Company::factory()->create();
        $foreignDivision = Division::create(['company_id' => $otherCompany->id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload($foreignDivision, [
                ['payroll_component_id' => $base->id, 'amount' => '2500000'],
            ]))
            ->assertSessionHasErrors('division_id');
    }

    public function test_a_component_from_another_company_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $otherCompany = Company::factory()->create();
        $foreignComponent = PayrollComponent::create([
            'company_id' => $otherCompany->id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $employee), $this->payload($division, [
                ['payroll_component_id' => $foreignComponent->id, 'amount' => '2500000'],
            ]))
            ->assertSessionHasErrors('components');
    }

    public function test_a_non_karyawan_user_cannot_be_targeted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd', 'company_id' => $hrd->company_id]);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);

        // A fully valid payload — this must 404 from the role check inside
        // the controller, not from validation rejecting an empty payload.
        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $otherHrd), $this->payload($division, [
                ['payroll_component_id' => $base->id, 'amount' => '2500000'],
            ]))
            ->assertNotFound();
    }

    public function test_hrd_cannot_view_or_edit_another_companys_employee_profile(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $otherEmployee = User::factory()->create(['company_id' => $otherHrd->company_id, 'role' => 'karyawan']);
        $otherDivision = Division::create(['company_id' => $otherHrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $otherComponent = PayrollComponent::create([
            'company_id' => $otherHrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.profil-gaji.edit', $otherEmployee))
            ->assertForbidden();

        $this->actingAs($hrd)
            ->put(route('hrd.profil-gaji.update', $otherEmployee), $this->payload($otherDivision, [
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
}
