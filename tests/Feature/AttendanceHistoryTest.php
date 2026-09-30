<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_their_own_attendance()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_time' => today()->setTime(8, 0),
            'clock_in_status' => 'tepat_waktu',
        ]);

        Attendance::create([
            'user_id' => $otherUser->id,
            'tanggal' => today(),
            'clock_in_time' => today()->setTime(8, 0),
            'clock_in_status' => 'tepat_waktu',
        ]);

        $attendances = $this->actingAs($user)
            ->get(route('riwayat-absen.index'))
            ->assertOk()
            ->viewData('page')['props']['attendances'];

        $this->assertCount(1, $attendances);
    }

    public function test_history_is_scoped_to_the_selected_month()
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'tanggal' => today()->subMonth(),
            'clock_in_time' => today()->subMonth()->setTime(8, 0),
            'clock_in_status' => 'tepat_waktu',
        ]);

        $currentMonth = $this->actingAs($user)
            ->get(route('riwayat-absen.index'))
            ->viewData('page')['props']['attendances'];

        $this->assertCount(0, $currentMonth);

        $lastMonth = $this->actingAs($user)
            ->get(route('riwayat-absen.index', ['bulan' => today()->subMonth()->format('Y-m')]))
            ->viewData('page')['props']['attendances'];

        $this->assertCount(1, $lastMonth);
    }

    public function test_malformed_month_query_param_does_not_crash()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('riwayat-absen.index', ['bulan' => 'not-a-date']))
            ->assertOk();
    }
}
