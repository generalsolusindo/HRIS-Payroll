<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->withEnrolledFace()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_karyawan_gets_the_clock_in_dashboard_not_the_hrd_summary(): void
    {
        $user = User::factory()->withEnrolledFace()->create(['role' => 'karyawan']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hrdSummary', null)
                ->has('todayAttendance')
                ->has('officeLocations')
            );
    }

    public function test_hrd_gets_a_company_wide_attendance_summary_instead(): void
    {
        $hrd = User::factory()->withEnrolledFace()->create(['role' => 'hrd']);

        $onTime = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        Attendance::create(['user_id' => $onTime->id, 'tanggal' => today(), 'clock_in_time' => now(), 'clock_in_status' => 'tepat_waktu']);

        $late = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        Attendance::create(['user_id' => $late->id, 'tanggal' => today(), 'clock_in_time' => now(), 'clock_in_status' => 'telat']);

        // Not clocked in yet today — still counts toward "total", not "sudah absen".
        User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // Must not be counted at all: wrong role, and inactive status.
        User::factory()->create(['role' => 'hrd', 'status' => 'active']);
        User::factory()->create(['role' => 'karyawan', 'status' => 'pending']);

        $this->actingAs($hrd)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hrdSummary.total_karyawan', 3)
                ->where('hrdSummary.sudah_absen', 2)
                ->where('hrdSummary.belum_absen', 1)
                ->where('hrdSummary.telat', 1)
                ->where('hrdSummary.pending_users', 1)
                ->missing('todayAttendance')
            );
    }
}
