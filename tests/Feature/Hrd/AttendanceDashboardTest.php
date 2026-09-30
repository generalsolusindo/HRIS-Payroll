<?php

namespace Tests\Feature\Hrd;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_sees_employees_who_have_not_clocked_in_today()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['status' => 'active', 'name' => 'Budi']);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.absensi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/attendance-dashboard'));

        $employees = $response->viewData('page')['props']['employees'];
        $row = collect($employees)->firstWhere('id', $employee->id);

        $this->assertSame('Budi', $row['name']);
        $this->assertNull($row['clock_in_time']);
    }

    public function test_hrd_sees_todays_attendance_record()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['status' => 'active']);

        Attendance::create([
            'user_id' => $employee->id,
            'tanggal' => today(),
            'clock_in_time' => today()->setTime(8, 45),
            'clock_in_status' => 'telat',
        ]);

        $employees = $this->actingAs($hrd)
            ->get(route('hrd.absensi.index'))
            ->viewData('page')['props']['employees'];

        $row = collect($employees)->firstWhere('id', $employee->id);

        $this->assertSame('telat', $row['clock_in_status']);
    }

    public function test_hrd_can_view_a_different_dates_attendance()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['status' => 'active']);

        Attendance::create([
            'user_id' => $employee->id,
            'tanggal' => today()->subDay(),
            'clock_in_time' => today()->subDay()->setTime(8, 0),
            'clock_in_status' => 'tepat_waktu',
        ]);

        $employees = $this->actingAs($hrd)
            ->get(route('hrd.absensi.index', ['tanggal' => today()->subDay()->toDateString()]))
            ->viewData('page')['props']['employees'];

        $row = collect($employees)->firstWhere('id', $employee->id);

        $this->assertSame('tepat_waktu', $row['clock_in_status']);
    }

    public function test_hrd_accounts_are_excluded_from_the_list()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $employees = $this->actingAs($hrd)
            ->get(route('hrd.absensi.index'))
            ->viewData('page')['props']['employees'];

        $this->assertNull(collect($employees)->firstWhere('id', $hrd->id));
    }

    public function test_karyawan_cannot_access_attendance_dashboard()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.absensi.index'))
            ->assertForbidden();
    }

    public function test_malformed_tanggal_query_param_does_not_crash()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('hrd.absensi.index', ['tanggal' => 'not-a-date']))
            ->assertOk();
    }
}
