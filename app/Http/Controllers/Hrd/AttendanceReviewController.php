<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\UpdateAttendanceReviewRequest;
use App\Models\AttendanceReview;
use App\Services\AttendanceReviewGenerator;
use App\Services\PayrollAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceReviewController extends Controller
{
    public function index(Request $request, AttendanceReviewGenerator $generator): Response
    {
        $date = $request->filled('tanggal')
            ? Carbon::parse($request->string('tanggal')->toString())->startOfDay()
            : today();

        abort_if($date->isFuture(), 422, 'Tidak bisa meninjau tanggal yang belum terjadi.');

        $generator->generateForDate($date);

        $reviews = AttendanceReview::whereDate('date', $date)
            ->with(['user:id,name,company_id,position_id', 'user.company:id,name', 'user.position:id,name'])
            ->get()
            ->sortBy('user.name')
            ->values()
            ->map(fn (AttendanceReview $review) => [
                'id' => $review->id,
                'user_name' => $review->user->name,
                'company_name' => $review->user->company->name,
                'position_name' => $review->user->position->name,
                'classification' => $review->classification,
                'status' => $review->status,
                'clock_in_at' => $review->clock_in_at?->toIso8601String(),
                'clock_out_at' => $review->clock_out_at?->toIso8601String(),
                'late_minutes' => $review->late_minutes,
                'early_departure_minutes' => $review->early_departure_minutes,
                'correction_reason' => $review->correction_reason,
                'has_evidence' => $review->evidence_path !== null,
            ]);

        return Inertia::render('hrd/attendance-reviews', [
            'tanggal' => $date->toDateString(),
            'reviews' => $reviews,
        ]);
    }

    public function update(UpdateAttendanceReviewRequest $request, AttendanceReview $review, PayrollAuditLogger $auditLogger): RedirectResponse
    {
        if ($review->status === 'validated' && $review->correction_reason !== null) {
            // Already hand-corrected once — further changes go through the
            // same form again, but this guard exists so a stale tab can't
            // silently clobber a newer correction without even noticing.
            $incomingVersion = $request->integer('expected_version');
            if ($incomingVersion !== 0 && $incomingVersion !== $review->version) {
                throw ValidationException::withMessages([
                    'classification' => 'Data ini sudah diubah pihak lain sejak halaman dimuat. Muat ulang halaman.',
                ]);
            }
        }

        $validated = $request->validated();

        $before = ['classification' => $review->classification, 'correction_reason' => $review->correction_reason];

        $evidencePath = $review->evidence_path;
        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store(
                $review->user_id.'/'.$review->date->toDateString(),
                'attendance_evidence',
            );
        }

        $review->fill([
            'classification' => $validated['classification'],
            'correction_reason' => $validated['correction_reason'],
            'evidence_path' => $evidencePath,
            'status' => 'validated',
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'version' => $review->version + 1,
        ])->save();

        $auditLogger->log(
            companyId: $review->company_id,
            entityType: 'AttendanceReview',
            entityId: $review->id,
            action: 'corrected',
            before: $before,
            after: ['classification' => $review->classification, 'correction_reason' => $review->correction_reason],
            reason: $validated['correction_reason'],
            attendanceReviewId: $review->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Koreksi absensi disimpan.']);

        return back();
    }
}
