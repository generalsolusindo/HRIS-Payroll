<?php

namespace Tests\Feature\Hrd;

use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $companyId, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $companyId,
            'code' => '2026-10',
            'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26',
            'attendance_ends_on' => '2026-10-25',
        ], $overrides);
    }

    public function test_hrd_can_create_a_payroll_period(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($hrd->company_id))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('payroll_periods', ['code' => '2026-10', 'name' => 'Oktober 2026']);
    }

    public function test_period_code_must_be_unique_within_a_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($hrd->company_id, ['name' => 'Lagi']))
            ->assertSessionHasErrors('code');
    }

    public function test_ends_on_must_not_be_before_starts_on(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($hrd->company_id, [
                'starts_on' => '2026-10-31', 'ends_on' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('ends_on');
    }

    public function test_karyawan_cannot_access_payroll_period_management(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.periode-gaji.index'))
            ->assertForbidden();
    }
}
