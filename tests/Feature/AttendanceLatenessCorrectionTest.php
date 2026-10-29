<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendancePunctuality;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLatenessCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_wib_boundary_and_minutes_are_independent_of_input_timezone(): void
    {
        $calculator = new AttendancePunctuality;
        foreach ([
            ['01:29:59', 'tepat_waktu', null],
            ['01:30:00', 'tepat_waktu', null],
            ['01:30:01', 'tepat_waktu', null],
            ['01:30:59', 'tepat_waktu', null],
            ['01:31:00', 'telat', 1],
            ['01:34:00', 'telat', 4],
            ['01:34:59', 'telat', 4],
            ['01:35:00', 'telat', 5],
        ] as [$time, $status, $minutes]) {
            $result = $calculator->evaluate(CarbonImmutable::parse('2026-10-03 '.$time, 'UTC'), '08:30:00');
            $this->assertSame(['status' => $status, 'minutes' => $minutes], $result);
        }
    }

    public function test_correction_supports_preview_and_idempotent_apply_without_changing_punch_time(): void
    {
        $user = User::factory()->create();
        WorkSchedule::create([
            'company_id' => $user->company_id, 'name' => 'Reguler',
            'jam_masuk' => '08:30:00', 'jam_pulang' => '16:30:00', 'toleransi_telat_menit' => 15,
        ]);
        $attendance = Attendance::create([
            'user_id' => $user->id, 'tanggal' => '2026-10-03',
            'clock_in_time' => '2026-10-03 08:34:00', 'clock_in_status' => 'tepat_waktu',
        ]);
        $this->artisan('attendance:recalculate-lateness 2026-10-03')
            ->expectsOutput('Pratinjau: 1 catatan; dilewati: 0.')->assertSuccessful();
        $this->assertSame('tepat_waktu', $attendance->fresh()->clock_in_status);
        $this->artisan('attendance:recalculate-lateness 2026-10-03 --apply')->assertSuccessful();
        $this->assertSame('telat', $attendance->fresh()->clock_in_status);
        $this->assertSame(4, $attendance->fresh()->clock_in_late_minutes);
        $this->assertSame('08:34:00', $attendance->fresh()->clock_in_time->format('H:i:s'));
        $this->artisan('attendance:recalculate-lateness 2026-10-03 --apply')
            ->expectsOutput('Diperbaiki: 0 catatan; dilewati: 0.')->assertSuccessful();
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->artisan('attendance:recalculate-lateness 2026-02-30')->assertFailed();
    }
}
