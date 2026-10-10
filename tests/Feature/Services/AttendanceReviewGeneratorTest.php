<?php

namespace Tests\Feature\Services;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\CompanyCalendarDay;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceReviewGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceReviewGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): AttendanceReviewGenerator
    {
        return app(AttendanceReviewGenerator::class);
    }

    public function test_sunday_becomes_a_rest_day_without_an_attendance_row(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        // 2026-10-04 is a Sunday.
        $date = Carbon::parse('2026-10-04');

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('rest_day', $review->classification);
        $this->assertSame('validated', $review->status);
    }

    public function test_a_calendar_override_takes_precedence_over_the_weekly_pattern(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $date = Carbon::parse('2026-08-17'); // A Monday.
        CompanyCalendarDay::create([
            'company_id' => $user->company_id, 'date' => $date, 'type' => 'holiday', 'description' => 'Hari Kemerdekaan',
        ]);

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('holiday', $review->classification);
    }

    public function test_an_approved_leave_is_reflected_without_requiring_attendance(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $date = Carbon::parse('2026-10-05'); // A Monday.
        LeaveRequest::create([
            'request_number' => 'REQ-1', 'user_id' => $user->id, 'type' => 'sick',
            'leave_date' => $date, 'status' => 'approved',
        ]);

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('sick', $review->classification);
        $this->assertSame('validated', $review->status);
    }

    public function test_a_workday_with_no_attendance_and_no_leave_is_automatically_absent(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $date = Carbon::parse('2026-10-05'); // A Monday.

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('absent', $review->classification);
        $this->assertSame('validated', $review->status);
    }

    public function test_clocking_in_without_clocking_out_requires_hrd_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        WorkSchedule::create([
            'company_id' => $user->company_id, 'name' => 'Reguler',
            'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00', 'toleransi_telat_menit' => 0,
        ]);
        $date = Carbon::parse('2026-10-05');
        Attendance::create([
            'user_id' => $user->id, 'tanggal' => $date,
            'clock_in_time' => '2026-10-05 08:40:00', 'clock_in_status' => 'telat', 'clock_in_late_minutes' => 5,
        ]);

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('unresolved', $review->classification);
        $this->assertSame('draft', $review->status);
        $this->assertSame(5, $review->late_minutes);
        $this->assertSame(0, $review->worked_minutes);
        $this->assertSame(0, $review->early_departure_minutes);
    }

    public function test_a_full_attendance_record_becomes_present_with_computed_minutes(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        WorkSchedule::create([
            'company_id' => $user->company_id, 'name' => 'Reguler',
            'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00', 'toleransi_telat_menit' => 0,
        ]);
        $date = Carbon::parse('2026-10-05');
        Attendance::create([
            'user_id' => $user->id, 'tanggal' => $date,
            'clock_in_time' => '2026-10-05 08:05:00', 'clock_in_status' => 'telat', 'clock_in_late_minutes' => 5,
            'clock_out_time' => '2026-10-05 16:50:00', 'clock_out_status' => 'pulang_cepat', 'clock_out_early_minutes' => 10,
        ]);

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('present', $review->classification);
        $this->assertSame('validated', $review->status);
        $this->assertSame(540, $review->scheduled_minutes);
        $this->assertSame(525, $review->worked_minutes);
        $this->assertSame(5, $review->late_minutes);
        $this->assertSame(10, $review->early_departure_minutes);
    }

    public function test_clocking_out_in_under_half_the_scheduled_shift_is_a_half_day(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        WorkSchedule::create([
            'company_id' => $user->company_id, 'name' => 'Reguler',
            'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00', 'toleransi_telat_menit' => 0,
        ]);
        $date = Carbon::parse('2026-10-05');
        // Scheduled shift is 540 minutes; clocking out after 200 is under half.
        Attendance::create([
            'user_id' => $user->id, 'tanggal' => $date,
            'clock_in_time' => '2026-10-05 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-10-05 11:20:00', 'clock_out_status' => 'pulang_cepat', 'clock_out_early_minutes' => 340,
        ]);

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('half_day', $review->classification);
        $this->assertSame('validated', $review->status);
        $this->assertSame(200, $review->worked_minutes);
    }

    public function test_working_at_least_half_the_scheduled_shift_still_counts_as_present(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        WorkSchedule::create([
            'company_id' => $user->company_id, 'name' => 'Reguler',
            'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00', 'toleransi_telat_menit' => 0,
        ]);
        $date = Carbon::parse('2026-10-05');
        // Exactly half the 540-minute shift — the boundary must still count
        // as present, not half_day (the rule is strictly *less than* half).
        Attendance::create([
            'user_id' => $user->id, 'tanggal' => $date,
            'clock_in_time' => '2026-10-05 08:00:00', 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => 0,
            'clock_out_time' => '2026-10-05 12:30:00', 'clock_out_status' => 'pulang_cepat', 'clock_out_early_minutes' => 270,
        ]);

        $this->generator()->generateForDate($date);

        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('present', $review->classification);
    }

    public function test_regenerating_never_overwrites_a_hand_corrected_review(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $date = Carbon::parse('2026-10-05');

        $this->generator()->generateForDate($date);
        $review = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
        $this->assertSame('absent', $review->classification);

        $review->fill([
            'classification' => 'sick', 'correction_reason' => 'Surat dokter dikirim via WhatsApp.',
            'status' => 'validated',
        ])->save();

        // Re-running the generator (e.g. the HRD screen reloading) must not
        // touch a row HR has already corrected by hand.
        $this->generator()->generateForDate($date);

        $this->assertSame('sick', $review->fresh()->classification);
        $this->assertSame('Surat dokter dikirim via WhatsApp.', $review->fresh()->correction_reason);
    }

    public function test_inactive_or_non_karyawan_users_are_not_reviewed(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd', 'status' => 'active']);
        $inactive = User::factory()->create(['role' => 'karyawan', 'status' => 'pending']);
        $date = Carbon::parse('2026-10-05');

        $this->generator()->generateForDate($date);

        $this->assertDatabaseMissing('attendance_reviews', ['user_id' => $hrd->id]);
        $this->assertDatabaseMissing('attendance_reviews', ['user_id' => $inactive->id]);
    }
}
