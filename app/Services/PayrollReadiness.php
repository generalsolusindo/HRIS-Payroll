<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PayrollReadiness
{
    /**
     * Attendance for a period's cutoff date is considered final at this
     * hour, not at midnight — HRD runs the recap at 15:00 on the cutoff
     * date itself (even when that date is the last day of the month),
     * so submission shouldn't have to wait for the date to fully end.
     * Company-wide for now; no per-company override exists.
     */
    private const CUTOFF_HOUR = 15;

    /** @return list<string> */
    public function issues(PayrollRun $run): array
    {
        $run->loadMissing('period', 'entries.lines');
        $issues = [];
        if ($run->entries->isEmpty()) {
            $issues[] = 'Belum ada karyawan dalam hasil payroll.';
        }
        // Periode absensi belum selesai jika batas waktu cutoff (15:00) belum lewat,
        // KECUALI jika semua karyawan sudah dikonfirmasi jam pulang — ditandai dengan
        // tidak adanya AttendanceReview ber-classification 'unresolved' di periode ini.
        if ($run->period->attendance_ends_on->copy()->setTime(self::CUTOFF_HOUR, 0)->isFuture()) {
            $hasUnresolved = AttendanceReview::where('company_id', $run->period->company_id)
                ->whereBetween('date', [
                    $run->period->attendance_starts_on->toDateString(),
                    $run->period->attendance_ends_on->toDateString(),
                ])
                ->where('classification', 'unresolved')
                ->exists();

            if ($hasUnresolved) {
                $issues[] = 'Periode absensi belum selesai.';
            }
        }

        $missing = User::where('company_id', $run->period->company_id)->where('role', 'karyawan')->where('status', 'active')
            ->where('is_payroll_eligible', true)
            ->whereNotIn('id', $run->entries->pluck('user_id'))->count();
        if ($missing > 0) {
            $issues[] = "$missing karyawan aktif belum masuk payroll. Lengkapi profil gaji dan hitung ulang draft.";
        }
        $expectedDates = [];
        for ($date = $run->period->attendance_starts_on->copy(); $date->lte($run->period->attendance_ends_on); $date = $date->addDay()) {
            $expectedDates[] = $date->toDateString();
        }
        $calendar = app(CompanyWorkCalendar::class);
        $totals = ['earning' => '0.00', 'deduction' => '0.00', 'net' => '0.00'];
        foreach ($run->entries as $entry) {
            $name = $entry->employee_snapshot['name'] ?? 'Karyawan';
            $days = $entry->attendance_snapshot['days'] ?? [];
            $dates = array_column($days, 'date');
            if (count($dates) !== count($expectedDates) || array_diff($expectedDates, $dates) !== [] || ($entry->attendance_snapshot['unresolved_days'] ?? 0) > 0) {
                $issues[] = "$name: rekap absensi belum lengkap. Koreksi absensi lalu hitung ulang draft.";
            }
            $reviews = AttendanceReview::where('user_id', $entry->user_id)->whereBetween('date', [$run->period->attendance_starts_on->toDateString(), $run->period->attendance_ends_on->toDateString()])->get()->keyBy('id');
            $missingOut = Attendance::where('user_id', $entry->user_id)
                ->whereDate('tanggal', '>=', $run->period->attendance_starts_on->toDateString())
                ->whereDate('tanggal', '<=', $run->period->attendance_ends_on->toDateString())
                ->whereNotNull('clock_in_time')->whereNull('clock_out_time')->get();
            foreach ($missingOut as $attendance) {
                if (! $calendar->isWorkday($run->period->company_id, $attendance->tanggal, $entry->user_id)) {
                    continue;
                }

                $confirmed = $reviews->first(fn (AttendanceReview $review) => $review->date->toDateString() === $attendance->tanggal->toDateString());
                // Approved leave/non-working days override an incidental clock-in.
                if ($confirmed && in_array($confirmed->classification, ['sick', 'leave', 'permission', 'business_trip', 'rest_day', 'holiday'], true)) {
                    continue;
                }
                if (! $confirmed || ! $confirmed->correction_reason || ! $confirmed->validated_by
                    || $confirmed->status !== 'validated' || ! $confirmed->clock_out_at) {
                    $issues[] = "$name: absen pulang belum dikonfirmasi HRD. Buka Rekap Periode lalu perbarui draft.";
                    break;
                }
            }
            foreach ($days as $day) {
                $currentDayType = $calendar->dayType($run->period->company_id, $day['date'], $entry->user_id);
                if (($day['calendar_day_type'] ?? null) !== $currentDayType) {
                    $issues[] = "$name: kalender kerja berubah sejak payroll dihitung. Hitung ulang draft.";
                    break;
                }

                // Off-day reviews are informational only. They must exist in
                // the period snapshot, but attendance corrections/punches on
                // those dates must neither block payroll nor affect its totals.
                if ($currentDayType !== 'workday') {
                    continue;
                }

                $review = $reviews->get($day['attendance_review_id'] ?? 0);
                if (! $review || $review->version !== ($day['attendance_review_version'] ?? null)
                    || $review->status !== 'validated' || $review->classification === 'unresolved'
                    || $review->classification !== ($day['classification'] ?? null)
                    || $review->late_minutes !== ($day['late_minutes'] ?? null)
                    || $review->worked_minutes !== ($day['worked_minutes'] ?? null)) {
                    $issues[] = "$name: rekap absensi berubah atau belum divalidasi. Hitung ulang draft setelah koreksi.";
                    break;
                }
            }
            $expectedLate = $reviews
                ->filter(fn (AttendanceReview $review) => $calendar->isWorkday($run->period->company_id, $review->date, $entry->user_id))
                ->whereIn('classification', ['present', 'half_day'])
                ->reject(fn (AttendanceReview $review) => $review->isLateDeductionExcused())
                ->sum('late_minutes');
            if ($expectedLate !== ($entry->attendance_snapshot['late_minutes_total'] ?? 0)) {
                $issues[] = "$name: total menit telat berubah. Perbarui draft payroll.";
            }
            $overtimeRate = PayrollMoney::decimal((string) ($run->policy_snapshot['rules']['overtime']['rate_per_hour'] ?? '0'));
            if (bccomp($overtimeRate, '0', 2) > 0) {
                $approvedMinutes = (int) OvertimeRequest::where('user_id', $entry->user_id)->where('status', 'approved')
                    ->whereBetween('starts_at', [$run->period->attendance_starts_on->startOfDay(), $run->period->attendance_ends_on->endOfDay()])->sum('approved_minutes');
                $line = $entry->lines->firstWhere('source', 'overtime');
                if ($approvedMinutes !== ($line?->calculation_snapshot['approved_minutes'] ?? 0)) {
                    $issues[] = "$name: persetujuan lembur berubah. Hitung ulang draft.";
                }
            }
            $gross = '0.00';
            $deduction = '0.00';
            foreach ($entry->lines as $line) {
                if ($line->type === 'earning') {
                    $gross = bcadd($gross, PayrollMoney::decimal($line->amount), 2);
                } elseif ($line->type === 'deduction') {
                    $deduction = bcadd($deduction, PayrollMoney::decimal($line->amount), 2);
                }
            }
            $net = bcsub($gross, $deduction, 2);
            if ($entry->lines->isEmpty() || bccomp($gross, PayrollMoney::decimal($entry->gross_amount), 2) !== 0
                || bccomp($deduction, PayrollMoney::decimal($entry->deduction_amount), 2) !== 0
                || bccomp($net, $entry->net_amount, 2) !== 0 || bccomp($net, '0', 2) < 0) {
                $issues[] = "$name: rincian nominal tidak konsisten atau gaji bersih negatif.";
            }
            $totals['earning'] = bcadd($totals['earning'], $gross, 2);
            $totals['deduction'] = bcadd($totals['deduction'], $deduction, 2);
            $totals['net'] = bcadd($totals['net'], $net, 2);
        }
        if ($run->employee_count !== $run->entries->count() || bccomp($totals['earning'], PayrollMoney::decimal($run->gross_total), 2) !== 0
            || bccomp($totals['deduction'], PayrollMoney::decimal($run->deduction_total), 2) !== 0
            || bccomp($totals['net'], PayrollMoney::decimal($run->net_total), 2) !== 0) {
            $issues[] = 'Total perusahaan tidak cocok dengan rincian karyawan. Hitung ulang draft.';
        }

        return array_values(array_unique($issues));
    }

    public function assertReady(PayrollRun $run): void
    {
        $issues = $this->issues($run);
        if ($issues !== []) {
            throw ValidationException::withMessages(['readiness' => $issues]);
        }
    }
}
