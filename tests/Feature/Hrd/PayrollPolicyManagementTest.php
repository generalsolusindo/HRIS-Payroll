<?php

namespace Tests\Feature\Hrd;

use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPolicyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_the_page_creates_a_default_zero_rate_policy_per_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('hrd.kebijakan-absensi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/attendance-policy')
                ->has('policy')
            );

        $this->assertDatabaseHas('payroll_policies', [
            'company_id' => $hrd->company_id, 'code' => 'REGULAR', 'version' => 1,
        ]);
    }

    public function test_hrd_can_set_attendance_and_overtime_rates(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $policy = PayrollPolicy::create([
            'company_id' => $hrd->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => [],
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.kebijakan-absensi.update', $policy), [
                'late_deduction_per_minute' => '2000',
                'perfect_attendance_reward' => '100000',
                'allowance_per_day' => '50000',
                'transport_per_day' => '12000',
                'overtime_rate_per_hour' => '25000',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $rules = $policy->fresh()->rules['attendance'];
        $this->assertSame('2000.00', $rules['late_deduction_per_minute']);
        $this->assertSame('100000.00', $rules['perfect_attendance_reward']);
        $this->assertSame('50000.00', $rules['allowance_per_day']);
        $this->assertSame('12000.00', $rules['transport_per_day']);
        $this->assertSame('25000.00', $policy->fresh()->rules['overtime']['rate_per_hour']);
    }

    public function test_rates_must_be_non_negative_numbers(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $policy = PayrollPolicy::create([
            'company_id' => $hrd->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => [],
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.kebijakan-absensi.update', $policy), [
                'late_deduction_per_minute' => '-1',
                'perfect_attendance_reward' => '100000',
            ])
            ->assertSessionHasErrors('late_deduction_per_minute');
    }

    public function test_hrd_cannot_edit_another_companys_policy(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $policy = PayrollPolicy::create([
            'company_id' => $otherHrd->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => [],
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.kebijakan-absensi.update', $policy), [
                'late_deduction_per_minute' => '2000',
                'perfect_attendance_reward' => '100000',
                'allowance_per_day' => '50000',
                'transport_per_day' => '12000',
                'overtime_rate_per_hour' => '25000',
            ])
            ->assertForbidden();

        $this->assertSame([], $policy->fresh()->rules);
    }

    public function test_karyawan_cannot_view_or_edit_attendance_policy(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $policy = PayrollPolicy::create([
            'company_id' => $karyawan->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => [],
        ]);

        $this->actingAs($karyawan)
            ->get(route('hrd.kebijakan-absensi.index'))
            ->assertForbidden();

        $this->actingAs($karyawan)
            ->put(route('hrd.kebijakan-absensi.update', $policy), [
                'late_deduction_per_minute' => '1',
                'perfect_attendance_reward' => '1',
            ])
            ->assertForbidden();
    }
}
