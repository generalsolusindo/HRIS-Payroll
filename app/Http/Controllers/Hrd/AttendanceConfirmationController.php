<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Services\PayrollAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceConfirmationController extends Controller
{
    use ScopesToOwnCompany;

    public function update(Request $request, AttendanceReview $review, PayrollAuditLogger $audit): RedirectResponse
    {
        $this->assertOwnCompany($request, $review->company_id);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'clock_in' => ['sometimes', 'nullable', 'date_format:H:i'],
            'clock_out' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $review, $data, $audit): void {
            $locked = AttendanceReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            $this->assertOwnCompany($request, $locked->company_id);
            $attendance = Attendance::whereKey($locked->attendance_id)->lockForUpdate()->first();
            if ($locked->version !== (int) $data['version'] || $locked->classification !== 'unresolved'
                || $locked->correction_reason !== null || ! $locked->clock_in_at || ! $attendance
                || $attendance->clock_out_time || ! $attendance->clock_in_time
                || ! $attendance->clock_in_time->equalTo($locked->clock_in_at)) {
                throw ValidationException::withMessages(['confirmation' => 'Data absensi berubah atau sudah dikonfirmasi. Muat ulang rekap.']);
            }
            if ($locked->scheduled_minutes <= 0 || ! isset($locked->schedule_snapshot['jam_masuk'], $locked->schedule_snapshot['jam_pulang'])) {
                throw ValidationException::withMessages(['confirmation' => 'Jadwal kerja belum lengkap. Lengkapi jadwal lalu muat ulang rekap.']);
            }

            $in = CarbonImmutable::parse($locked->date->toDateString().' '.($data['clock_in'] ?? $locked->clock_in_at->format('H:i')), 'Asia/Jakarta');
            $out = CarbonImmutable::parse($locked->date->toDateString().' '.$data['clock_out'], 'Asia/Jakarta');
            if ($in->greaterThanOrEqualTo($out)) {
                throw ValidationException::withMessages(['clock_out' => 'Jam pulang harus setelah jam masuk pada tanggal yang sama.']);
            }

            $scheduledIn = CarbonImmutable::parse($locked->date->toDateString().' '.$locked->schedule_snapshot['jam_masuk'], 'Asia/Jakarta');
            $scheduledOut = CarbonImmutable::parse($locked->date->toDateString().' '.$locked->schedule_snapshot['jam_pulang'], 'Asia/Jakarta');
            $lateMinutes = $in->gt($scheduledIn) ? max(1, (int) $scheduledIn->diffInMinutes($in)) : 0;
            $minutes = (int) $in->diffInMinutes($out);

            $before = $locked->only(['status', 'classification', 'worked_minutes', 'clock_in_at', 'clock_out_at', 'late_minutes', 'version']);
            $locked->fill([
                'status' => 'validated',
                'classification' => $minutes < $locked->scheduled_minutes / 2 ? 'half_day' : 'present',
                'clock_in_at' => $in,
                'clock_out_at' => $out,
                'worked_minutes' => $minutes,
                'late_minutes' => $lateMinutes,
                'early_departure_minutes' => $out->lt($scheduledOut) ? max(1, (int) $scheduledOut->diffInMinutes($out)) : 0,
                'correction_reason' => $data['reason'],
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
                'version' => $locked->version + 1,
            ])->save();
            $audit->log($locked->company_id, 'AttendanceReview', $locked->id, 'clock_out_confirmed', $before,
                $locked->only(['status', 'classification', 'worked_minutes', 'clock_in_at', 'clock_out_at', 'late_minutes', 'version']),
                $data['reason'], attendanceReviewId: $locked->id);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Absensi dikonfirmasi. Perbarui draft payroll sebelum diajukan.']);

        return back();
    }
}
