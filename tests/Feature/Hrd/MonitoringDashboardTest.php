<?php

namespace Tests\Feature\Hrd;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_karyawan_cannot_view_the_monitoring_dashboard(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.monitoring.index'))
            ->assertForbidden();
    }

    public function test_karyawan_aktif_count_only_includes_active_karyawan(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'pending']);
        User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'hrd', 'status' => 'active']);

        $this->actingAs($hrd)
            ->get(route('hrd.monitoring.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/monitoring-dashboard')
                ->where('stats.karyawan_aktif', 1)
                ->where('stats.kandidat_aktif', null)
                ->where('stats.pkl_aktif', null)
            );
    }

    public function test_an_employee_missing_ktp_and_kk_shows_up_twice_in_the_attention_list(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active', 'name' => 'Budi']);

        $this->actingAs($hrd)
            ->get(route('hrd.monitoring.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.dokumen_lengkap_percent', 0)
                ->where('attention', fn ($items) => collect($items)
                    ->where('name', 'Budi')
                    ->pluck('monitoring')
                    ->sort()
                    ->values()
                    ->all() === ['Dokumen KK', 'Dokumen KTP']
                )
            );
    }

    public function test_an_employee_with_both_baseline_documents_counts_toward_completeness(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);

        foreach (['ktp', 'kk'] as $type) {
            EmployeeDocument::create([
                'user_id' => $employee->id, 'type' => $type,
                'file_name' => "{$type}.jpg", 'file_path' => "fake/{$type}.jpg",
                'mime_type' => 'image/jpeg', 'file_size' => 100,
            ]);
        }

        $this->actingAs($hrd)
            ->get(route('hrd.monitoring.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.dokumen_lengkap_percent', 100)
                ->where('attention', fn ($items) => collect($items)->where('monitoring', 'Dokumen KTP')->isEmpty()
                    && collect($items)->where('monitoring', 'Dokumen KK')->isEmpty())
            );
    }

    public function test_a_probation_ending_soon_appears_as_trial_selesai_with_amber_severity(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active',
            'name' => 'Arry', 'probation_ends_on' => today()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.monitoring.index'))
            ->assertInertia(fn ($page) => $page
                ->where('attention', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['name'] === 'Arry'
                        && $item['monitoring'] === 'Trial selesai'
                        && $item['days_left'] === 3
                        && $item['severity'] === 'amber'
                ))
            );
    }

    public function test_a_contract_ending_further_out_appears_with_green_severity(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active',
            'name' => 'Andini', 'contract_ends_on' => today()->addDays(37)->toDateString(),
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.monitoring.index'))
            ->assertInertia(fn ($page) => $page
                ->where('attention', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['name'] === 'Andini'
                        && $item['monitoring'] === 'Kontrak'
                        && $item['days_left'] === 37
                        && $item['severity'] === 'green'
                ))
            );
    }

    public function test_an_overdue_deadline_appears_with_red_severity(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active',
            'name' => 'Overdue Pegawai', 'probation_ends_on' => today()->subDays(2)->toDateString(),
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.monitoring.index'))
            ->assertInertia(fn ($page) => $page
                ->where('attention', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['name'] === 'Overdue Pegawai'
                        && $item['days_left'] === -2
                        && $item['severity'] === 'red'
                ))
            );
    }

    public function test_a_deadline_far_beyond_the_window_is_not_included(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active',
            'name' => 'Masih Lama', 'contract_ends_on' => today()->addDays(200)->toDateString(),
        ]);
        // Baseline documents on hand so only the contract window is under
        // test here — otherwise this employee would still show up for the
        // (unrelated) missing-document reason.
        foreach (['ktp', 'kk'] as $type) {
            EmployeeDocument::create([
                'user_id' => $employee->id, 'type' => $type,
                'file_name' => "{$type}.jpg", 'file_path' => "fake/{$type}.jpg",
                'mime_type' => 'image/jpeg', 'file_size' => 100,
            ]);
        }

        $this->actingAs($hrd)
            ->get(route('hrd.monitoring.index'))
            ->assertInertia(fn ($page) => $page
                ->where('attention', fn ($items) => collect($items)->where('name', 'Masih Lama')->isEmpty())
            );
    }
}
