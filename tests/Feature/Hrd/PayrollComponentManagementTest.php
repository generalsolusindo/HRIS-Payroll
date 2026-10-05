<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollComponentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_can_create_a_payroll_component(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.komponen-gaji.store'), [
                'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
                'type' => 'earning', 'category' => 'base_salary', 'is_active' => true,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('payroll_components', ['code' => 'BASE', 'name' => 'Gaji Pokok']);
    }

    public function test_component_code_must_be_unique_within_a_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.komponen-gaji.store'), [
                'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok 2',
                'type' => 'earning', 'category' => 'base_salary', 'is_active' => true,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_an_invalid_type_or_category_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.komponen-gaji.store'), [
                'company_id' => $hrd->company_id, 'code' => 'X', 'name' => 'X',
                'type' => 'not-a-real-type', 'category' => 'base_salary', 'is_active' => true,
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_a_component_still_used_by_a_salary_line_cannot_be_deleted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $component = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'division_id' => Division::create([
                'company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional',
            ])->id,
            'position_id' => $employee->position_id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-10-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id,
            'payroll_component_id' => $component->id,
            'amount' => '2500000.00', 'basis' => 'monthly',
        ]);

        $this->actingAs($hrd)
            ->delete(route('hrd.komponen-gaji.destroy', $component))
            ->assertSessionHasErrors('component');

        $this->assertModelExists($component);
    }

    public function test_karyawan_cannot_access_payroll_component_management(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.komponen-gaji.index'))
            ->assertForbidden();
    }
}
