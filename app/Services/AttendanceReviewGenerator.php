<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Generates payroll reviews from attendance, approved leave and calendars.
 * Missing clock-out requires an HRD confirmation; manual corrections survive
 * regeneration so payroll can retain an auditable source.
 */
class AttendanceReviewGenerator
{
    /**
     * These leave types mean the employee is away from the office for the
     * whole day — the day is classified straight from the leave, real
     * attendance is never consulted, and the day never counts as present/
     * half_day/absent (so it never earns Tunjangan Kehadiran/Transport and
     * never costs Potongan Alpha).
     */
    private const FULL_DAY_LEAVE_TYPES = ['leave', 'sick', 'emergency'];

    /**
     * These leave types mean the employee still comes in (late, leaves
     * early, or steps out briefly) — real attendance still drives the
     * day's classification normally. The approved leave is only recorded
     * alongside it so payroll can excuse specific consequences (e.g. the
     * late deduction for an approved Izin Terlambat) without touching
     * Tunjangan Kehadiran/Transport eligibility.
     */
    private const IN_OFFICE_LEAVE_TYPES = ['late', 'half_day', 'temporary_exit'];

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

        $dayType = app(CompanyWorkCalendar::class)->dayType($user->company_id, $date, $user->id);

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

        $fullDayLeave = $this->findFullDayLeave($user->id, $date);

        if ($fullDayLeave) {
            $this->upsert($existing, $user, $date, [
                'status' => 'validated',
                'classification' => match ($fullDayLeave->type) {
                    'sick' => 'sick',
                    'leave' => 'leave',
                    default => 'permission', // emergency
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
                'leave_snapshot' => ['id' => $fullDayLeave->id, 'type' => $fullDayLeave->type, 'reason' => $fullDayLeave->reason],
                'schedule_snapshot' => $scheduleSnapshot,
            ]);

            return;
        }

        // An approved "still comes to the office" leave (telat/setengah
        // hari/keluar sebentar) never overrides real attendance below —
        // it only rides along as leave_snapshot so payroll can excuse a
        // specific consequence (the late deduction for telat) once the
        // day's classification is computed from the actual clock-in/out.
        $inOfficeLeave = LeaveRequest::where('user_id', $user->id)
            ->whereDate('leave_date', $date)
            ->whereIn('type', self::IN_OFFICE_LEAVE_TYPES)
            ->where('status', 'approved')
            ->first();
        $leaveSnapshot = $inOfficeLeave
            ? ['id' => $inOfficeLeave->id, 'type' => $inOfficeLeave->type, 'reason' => $inOfficeLeave->reason]
            : null;

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
                'leave_snapshot' => $leaveSnapshot,
                'schedule_snapshot' => $scheduleSnapshot,
            ]);

            return;
        }

        if (! $attendance->clock_out_time) {
            // Do not invent worked hours: HRD must confirm the missing clock-out.
            $this->upsert($existing, $user, $date, [
                'status' => 'draft',
                'classification' => 'unresolved',
                'scheduled_minutes' => $scheduledMinutes,
                'worked_minutes' => 0,
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
                'leave_snapshot' => $leaveSnapshot,
                'schedule_snapshot' => $scheduleSnapshot,
            ]);

            return;
        }

        $workedMinutes = (int) $attendance->clock_in_time->diffInMinutes($attendance->clock_out_time);
        // Clocked in and out, but for less than half the scheduled shift —
        // counted as a half day rather than a full present day.
        $classification = $scheduledMinutes > 0 && $workedMinutes < $scheduledMinutes / 2 ? 'half_day' : 'present';

        $this->upsert($existing, $user, $date, [
            'status' => 'validated',
            'classification' => $classification,
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
            'leave_snapshot' => $leaveSnapshot,
            'schedule_snapshot' => $scheduleSnapshot,
        ]);
    }

    /**
     * "leave" and "sick" can span several days via their duration field;
     * "emergency" is always a single day (same-day only, per
     * LeaveTypeValidator). The 30-day lookback covers the longest
     * possible Cuti so this never has to scan the whole table.
     */
    private function findFullDayLeave(int $userId, CarbonInterface $date): ?LeaveRequest
    {
        $dateString = $date->toDateString();

        return LeaveRequest::where('user_id', $userId)
            ->whereIn('type', self::FULL_DAY_LEAVE_TYPES)
            ->where('status', 'approved')
            // whereDate(), not where()/whereBetween() on the raw column —
            // the date cast stores a "00:00:00" time component, so a plain
            // string-range compare silently misses rows (see
            // AttendanceReviewGeneratorTest / this exact gotcha elsewhere
            // in this codebase).
            ->whereDate('leave_date', '>=', CarbonImmutable::parse($dateString)->subDays(30)->toDateString())
            ->whereDate('leave_date', '<=', $dateString)
            ->get()
            ->first(function (LeaveRequest $candidate) use ($dateString) {
                $days = $candidate->duration !== null ? (int) ceil((float) $candidate->duration) : 1;
                $endDate = CarbonImmutable::parse($candidate->leave_date)->addDays(max($days - 1, 0));

                return $dateString >= $candidate->leave_date->toDateString() && $dateString <= $endDate->toDateString();
            });
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

        DB::transaction(function () use ($review, $attributes): void {
            $current = $review->exists ? AttendanceReview::whereKey($review->id)->lockForUpdate()->firstOrFail() : $review;
            if ($current->correction_reason !== null) {
                return; // A concurrent HRD confirmation takes precedence.
            }
            $current->fill($attributes);
            if ($current->exists && $current->isDirty()) {
                $current->version++;
            }
            $current->save();
        });
    }

    private function minutesBetween(string $start, string $end): int
    {
        $startsAt = CarbonImmutable::createFromTimeString($start);
        $endsAt = CarbonImmutable::createFromTimeString($end);

        return max(0, (int) $startsAt->diffInMinutes($endsAt));
    }
}
