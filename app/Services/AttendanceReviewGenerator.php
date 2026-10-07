<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\CompanyCalendarDay;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns the app's own attendance (selfie+GPS clock-in/out) and approved
 * leave requests into attendance_reviews — the record the payroll
 * calculator reads from. Fully automatic: a workday with neither an
 * attendance row nor an approved leave becomes "absent" (alpha) on its
 * own, and a clock-in with no matching clock-out is still counted as a
 * full present day (benefit of the doubt — there is proof they showed
 * up), so no HRD confirmation step ever blocks payroll. HRD can still
 * hand-correct an individual day afterwards (Koreksi Absensi) if the
 * automatic read was wrong; that correction is what's preserved below.
 *
 * Idempotent and safe to re-run: a review HR has already corrected
 * (correction_reason is set) is left untouched. Everything else is
 * regenerated fresh from the current source data every time — so an
 * employee who clocks in after this already ran for the day flips back
 * from "absent" to "present" on the next recalculation, not stuck stale.
 */
class AttendanceReviewGenerator
{
    public function generateForDate(CarbonInterface $date, ?int $companyId = null): void
    {
        $users = User::where('role', 'karyawan')
            ->where('status', 'active')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->with('company')
            ->get();

        foreach ($users as $user) {
            $this->generateForUserAndDate($user, $date);
        }
    }

    private function generateForUserAndDate(User $user, CarbonInterface $date): void
    {
        // whereDate(), not where('date', $date): a plain where() binds the
        // connection's full datetime format, which only happens to match a
        // "date"-cast column under MySQL because it silently truncates the
        // time part on write — SQLite does not, so a raw equality compare
        // would otherwise miss rows depending on how they were written.
        // whereDate() truncates both sides consistently on every driver.
        $existing = AttendanceReview::where('user_id', $user->id)->whereDate('date', $date)->first();

        if ($existing && $existing->correction_reason !== null) {
            // HR already reviewed this one by hand — never silently
            // overwrite a human decision on regeneration.
            return;
        }

        $workSchedule = $user->company->workSchedules()->first();
        $scheduleSnapshot = $workSchedule ? [
            'jam_masuk' => $workSchedule->jam_masuk,
            'jam_pulang' => $workSchedule->jam_pulang,
        ] : [];
        $scheduledMinutes = $workSchedule ? $this->minutesBetween($workSchedule->jam_masuk, $workSchedule->jam_pulang) : 0;

        $dayType = $this->dayType($user, $date);

        if ($dayType !== 'workday') {
            $this->upsert($existing, $user, $date, [
                'status' => 'validated',
                'classification' => $dayType,
                'scheduled_minutes' => 0,
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
                'excused_minutes' => 0,
                'attendance_id' => null,
                'clock_in_at' => null,
                'clock_out_at' => null,
                'source_snapshot' => ['day_type' => $dayType],
                'leave_snapshot' => null,
                'schedule_snapshot' => $scheduleSnapshot,
            ]);

            return;
        }

        $leave = LeaveRequest::where('user_id', $user->id)
            ->whereDate('leave_date', $date)
            ->where('status', 'approved')
            ->first();

        if ($leave) {
            $this->upsert($existing, $user, $date, [
                'status' => 'validated',
                'classification' => match ($leave->type) {
                    'sick' => 'sick',
                    'leave' => 'leave',
                    default => 'permission',
                },
                'scheduled_minutes' => $scheduledMinutes,
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
                'excused_minutes' => $scheduledMinutes,
                'attendance_id' => null,
                'clock_in_at' => null,
                'clock_out_at' => null,
                'source_snapshot' => [],
                'leave_snapshot' => ['id' => $leave->id, 'type' => $leave->type, 'reason' => $leave->reason],
                'schedule_snapshot' => $scheduleSnapshot,
            ]);

            return;
        }

        $attendance = Attendance::where('user_id', $user->id)->whereDate('tanggal', $date)->first();

        if (! $attendance || ! $attendance->clock_in_time) {
            // No attendance row and no approved leave for a workday —
            // automatically alpha. If this is wrong (a late-filed leave,
            // a technical glitch), HRD can still hand-correct it
            // afterwards in Koreksi Absensi.
            $this->upsert($existing, $user, $date, [
                'status' => 'validated',
                'classification' => 'absent',
                'scheduled_minutes' => $scheduledMinutes,
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
                'excused_minutes' => 0,
                'attendance_id' => null,
                'clock_in_at' => null,
                'clock_out_at' => null,
                'source_snapshot' => [],
                'leave_snapshot' => null,
                'schedule_snapshot' => $scheduleSnapshot,
            ]);

            return;
        }

        if (! $attendance->clock_out_time) {
            // Clocked in, never clocked out — counted as a full present
            // day (benefit of the doubt: there is proof they showed up),
            // not an automatic deduction.
            $this->upsert($existing, $user, $date, [
                'status' => 'validated',
                'classification' => 'present',
                'scheduled_minutes' => $scheduledMinutes,
                'worked_minutes' => $scheduledMinutes,
                'late_minutes' => $attendance->clock_in_late_minutes ?? 0,
                'early_departure_minutes' => 0,
                'excused_minutes' => 0,
                'attendance_id' => $attendance->id,
                'clock_in_at' => $attendance->clock_in_time,
                'clock_out_at' => null,
                'source_snapshot' => [
                    'clock_in_status' => $attendance->clock_in_status,
                    'clock_in_late_minutes' => $attendance->clock_in_late_minutes,
                    'clock_out_missing' => true,
                ],
                'leave_snapshot' => null,
                'schedule_snapshot' => $scheduleSnapshot,
            ]);

            return;
        }

        $workedMinutes = (int) $attendance->clock_in_time->diffInMinutes($attendance->clock_out_time);

        $this->upsert($existing, $user, $date, [
            'status' => 'validated',
            'classification' => 'present',
            'scheduled_minutes' => $scheduledMinutes,
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $attendance->clock_in_late_minutes ?? 0,
            'early_departure_minutes' => $attendance->clock_out_early_minutes ?? 0,
            'excused_minutes' => 0,
            'attendance_id' => $attendance->id,
            'clock_in_at' => $attendance->clock_in_time,
            'clock_out_at' => $attendance->clock_out_time,
            'source_snapshot' => [
                'clock_in_status' => $attendance->clock_in_status,
                'clock_in_late_minutes' => $attendance->clock_in_late_minutes,
                'clock_out_status' => $attendance->clock_out_status,
                'clock_out_early_minutes' => $attendance->clock_out_early_minutes,
            ],
            'leave_snapshot' => null,
            'schedule_snapshot' => $scheduleSnapshot,
        ]);
    }

    private function dayType(User $user, CarbonInterface $date): string
    {
        $override = CompanyCalendarDay::where('company_id', $user->company_id)
            ->whereDate('date', $date)
            ->first();

        if ($override) {
            return $override->type;
        }

        // Matches the seeded schedule's own documented hours (Mon-Sat,
        // Sunday closed) — see database/seeders/CompanySeeder.php. A real
        // per-company weekly pattern is out of scope for this tahap.
        return $date->isSunday() ? 'rest_day' : 'workday';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(?AttendanceReview $existing, User $user, CarbonInterface $date, array $attributes): void
    {
        $review = $existing ?? new AttendanceReview([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            // A plain date string, not the CarbonInterface object: assigning
            // an object to a custom-format ("date:Y-m-d") cast skips its
            // write-time formatting, so it would otherwise fall back to
            // Carbon's default __toString() ("Y-m-d H:i:s") and corrupt the
            // stored value's uniqueness/lookup behavior.
            'date' => $date->toDateString(),
        ]);

        $review->fill($attributes)->save();
    }

    private function minutesBetween(string $start, string $end): int
    {
        $startsAt = CarbonImmutable::createFromTimeString($start);
        $endsAt = CarbonImmutable::createFromTimeString($end);

        return max(0, (int) $startsAt->diffInMinutes($endsAt));
    }
}
