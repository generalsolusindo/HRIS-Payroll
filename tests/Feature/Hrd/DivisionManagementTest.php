<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_can_create_a_division(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.divisi.store'), [
                'company_id' => $hrd->company_id, 'code' => 'OPS',
                'name' => 'Operasional', 'is_active' => true,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('divisions', ['code' => 'OPS', 'name' => 'Operasional']);
    }

    public function test_division_code_must_be_unique_within_a_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);

        $this->actingAs($hrd)
            ->post(route('hrd.divisi.store'), [
                'company_id' => $hrd->company_id, 'code' => 'OPS',
                'name' => 'Operasional 2', 'is_active' => true,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_hrd_can_update_a_division(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);

        $this->actingAs($hrd)
            ->put(route('hrd.divisi.update', $division), [
                'company_id' => $hrd->company_id, 'code' => 'OPS',
                'name' => 'Operasional Baru', 'is_active' => false,
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $division->fresh();
        $this->assertSame('Operasional Baru', $fresh->name);
        $this->assertFalse($fresh->is_active);
    }

    public function test_a_division_still_used_by_an_employee_profile_cannot_be_deleted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
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
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);

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
