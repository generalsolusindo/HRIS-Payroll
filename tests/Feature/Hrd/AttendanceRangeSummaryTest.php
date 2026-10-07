<?php

namespace Tests\Feature\Hrd;

use App\Models\AttendanceReview;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRangeSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeReview(User $employee, string $date, array $overrides = []): AttendanceReview
    {
        return AttendanceReview::create(array_merge([
            'user_id' => $employee->id,
            'company_id' => $employee->company_id,
            'date' => $date,
            'status' => 'validated',
            'classification' => 'present',
            'scheduled_minutes' => 480,
            'worked_minutes' => 480,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
            'excused_minutes' => 0,
            'source_snapshot' => [],
            'schedule_snapshot' => [],
            'version' => 1,
        ], $overrides));
    }

    public function test_hrd_sees_aggregated_counts_for_the_selected_range(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);

        $this->makeReview($employee, '2026-10-01');
        $this->makeReview($employee, '2026-10-02', ['late_minutes' => 15]);
        $this->makeReview($employee, '2026-10-03', ['classification' => 'absent']);
        $this->makeReview($employee, '2026-10-04', ['classification' => 'permission']);
        $this->makeReview($employee, '2026-10-05', ['classification' => 'unresolved']);
        // 2026-10-06 intentionally has no review at all.

        $response = $this->actingAs($hrd)
            ->get(route('hrd.rekap-periode.index', ['dari' => '2026-10-01', 'sampai' => '2026-10-06']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/attendance-range-summary'));

        $row = collect($response->viewData('page')['props']['employees'])->firstWhere('id', $employee->id);

        $this->assertSame(2, $row['hadir']);
        $this->assertSame(1, $row['telat']);
        $this->assertSame(15, $row['telat_menit']);
        $this->assertSame(1, $row['alpha']);
        $this->assertSame(1, $row['izin']);
        $this->assertSame(1, $row['perlu_konfirmasi']);
        $this->assertSame(5, $row['reviewed_days']);
        $this->assertSame(6, $row['expected_days']);
    }

    public function test_hrd_only_sees_their_own_companys_employees(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherCompany = Company::factory()->create();
        $otherEmployee = User::factory()->create(['company_id' => $otherCompany->id, 'role' => 'karyawan', 'name' => 'Karyawan Perusahaan Lain']);
        $this->makeReview($otherEmployee, '2026-10-01');

        $response = $this->actingAs($hrd)
            ->get(route('hrd.rekap-periode.index', ['dari' => '2026-10-01', 'sampai' => '2026-10-01']))
            ->assertOk();

        $names = collect($response->viewData('page')['props']['employees'])->pluck('name');
        $this->assertFalse($names->contains('Karyawan Perusahaan Lain'));
    }

    public function test_defaults_to_the_current_month_when_no_range_given(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('hrd.rekap-periode.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dari', today()->startOfMonth()->toDateString())
                ->where('sampai', today()->toDateString())
            );
    }

    public function test_sampai_before_dari_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('hrd.rekap-periode.index', ['dari' => '2026-10-10', 'sampai' => '2026-10-01']))
            ->assertSessionHasErrors('sampai');
    }

    public function test_a_range_longer_than_92_days_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('hrd.rekap-periode.index', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']))
            ->assertSessionHasErrors('sampai');
    }

    public function test_karyawan_cannot_access_the_range_summary(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.rekap-periode.index'))
            ->assertForbidden();
    }
}
