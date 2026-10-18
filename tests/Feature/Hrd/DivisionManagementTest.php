<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Operasional',
            'is_active' => true,
            'base_salary' => '3000000',
        ], $overrides);
    }

    public function test_hrd_can_create_a_division(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.divisi.store'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('divisions', [
            'name' => 'Operasional', 'company_id' => $hrd->company_id,
            'base_salary' => '3000000.00',
        ]);
    }

    public function test_division_name_must_be_unique_within_a_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);

        $this->actingAs($hrd)
            ->post(route('hrd.divisi.store'), $this->payload(['base_salary' => '4000000']))
            ->assertSessionHasErrors('name');
    }

    public function test_hrd_can_update_a_division(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);

        $this->actingAs($hrd)
            ->put(route('hrd.divisi.update', $division), $this->payload([
                'name' => 'Operasional Baru', 'is_active' => false, 'base_salary' => '3500000',
            ]))
            ->assertSessionDoesntHaveErrors();

        $fresh = $division->fresh();
        $this->assertSame('Operasional Baru', $fresh->name);
        $this->assertFalse($fresh->is_active);
        $this->assertSame('3500000.00', $fresh->base_salary);
    }

    public function test_hrd_cannot_edit_or_delete_another_companys_division(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $otherHrd->company_id, 'name' => 'Operasional']);

        $this->actingAs($hrd)
            ->put(route('hrd.divisi.update', $division), $this->payload())
            ->assertForbidden();

        $this->actingAs($hrd)
            ->delete(route('hrd.divisi.destroy', $division))
            ->assertForbidden();

        $this->assertSame('Operasional', $division->fresh()->name);
    }

    public function test_hrd_only_sees_their_own_companys_divisions(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherCompany = Company::factory()->create();
        Division::create(['company_id' => $otherCompany->id, 'name' => 'Divisi Perusahaan Lain']);

        $response = $this->actingAs($hrd)->get(route('hrd.divisi.index'))->assertOk();

        $names = collect($response->viewData('page')['props']['divisions'])->pluck('name');
        $this->assertFalse($names->contains('Divisi Perusahaan Lain'));
    }

    public function test_a_division_created_for_another_company_is_ignored(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherCompany = Company::factory()->create();

        $this->actingAs($hrd)
            ->post(route('hrd.divisi.store'), $this->payload(['company_id' => $otherCompany->id]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('divisions', ['company_id' => $hrd->company_id]);
        $this->assertDatabaseMissing('divisions', ['company_id' => $otherCompany->id]);
    }

    public function test_a_division_still_used_by_an_employee_profile_cannot_be_deleted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-10-01',
            'employment_type' => 'permanent',
        ]);

        $this->actingAs($hrd)
            ->delete(route('hrd.divisi.destroy', $division))
            ->assertSessionHasErrors('division');

        $this->assertModelExists($division);
    }

    public function test_an_unused_division_can_be_deleted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);

        $this->actingAs($hrd)
            ->delete(route('hrd.divisi.destroy', $division))
            ->assertSessionDoesntHaveErrors();

        $this->assertModelMissing($division);
    }

    public function test_karyawan_cannot_access_division_management(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.divisi.index'))
            ->assertForbidden();
    }
}
