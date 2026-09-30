<?php

namespace Tests\Feature\Hrd;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_can_view_pending_employees()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $pending = User::factory()->create(['status' => 'pending']);

        $this->actingAs($hrd)
            ->get(route('hrd.karyawan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/employee-approval')
                ->has('pendingUsers', 1)
                ->where('pendingUsers.0.id', $pending->id)
            );
    }

    public function test_hrd_can_approve_a_pending_employee()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $pending = User::factory()->create(['status' => 'pending']);

        $this->actingAs($hrd)
            ->patch(route('hrd.karyawan.approve', $pending))
            ->assertRedirect();

        $this->assertSame('active', $pending->fresh()->status);
    }

    public function test_hrd_can_reject_a_pending_employee()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $pending = User::factory()->create(['status' => 'pending']);

        $this->actingAs($hrd)
            ->patch(route('hrd.karyawan.reject', $pending))
            ->assertRedirect();

        $this->assertSame('rejected', $pending->fresh()->status);
    }

    public function test_karyawan_cannot_access_employee_approval()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.karyawan.index'))
            ->assertForbidden();
    }
}
