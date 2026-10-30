<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\AttendanceReview;
use App\Models\User;
use App\Services\AttendanceReviewGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRangeSummaryController extends Controller
{
    /**
     * Generates (same automatic rules as payroll: present/absent/leave/
     * rest_day/etc., see AttendanceReviewGenerator) and then aggregates
     * attendance_reviews rows over a date range. This is now the only
     * place that generates reviews for an arbitrary range on demand —
     * payroll calculation generates its own window independently, but
     * nothing else does since Koreksi Absensi was removed, so a date a
     * payroll run never covered would otherwise simply have no row to
     * read here.
     */
    public function index(Request $request, AttendanceReviewGenerator $generator): Response
    {
        $today = Carbon::today()->toDateString();
        $dari = $request->string('dari')->toString() ?: Carbon::today()->startOfMonth()->toDateString();
        $sampai = $request->string('sampai')->toString() ?: $today;

        $request->merge(['dari' => $dari, 'sampai' => $sampai])->validate([
            'dari' => ['date_format:Y-m-d'],
            'sampai' => ['date_format:Y-m-d', 'after_or_equal:dari'],
        ]);

        if (Carbon::parse($dari)->diffInDays(Carbon::parse($sampai)) > 92) {
            throw ValidationException::withMessages(['sampai' => 'Rentang tanggal maksimal 92 hari sekali tampil.']);
        }

        $expectedDays = (int) Carbon::parse($dari)->diffInDays(Carbon::parse($sampai)) + 1;

        // Future dates have nothing to generate yet.
        $generationEnd = Carbon::parse($sampai)->min(Carbon::today());
        for ($date = Carbon::parse($dari); $date->lte($generationEnd); $date = $date->addDay()) {
            $generator->generateForDate($date, $request->user()->company_id);
        }

        $employees = User::where('company_id', $request->user()->company_id)
            ->where('role', 'karyawan')->where('status', 'active')
            ->with('division:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'division_id'])
            ->map(function (User $user) use ($dari, $sampai, $expectedDays) {
                $reviews = AttendanceReview::where('user_id', $user->id)
                    ->whereBetween('date', [$dari, $sampai])
                    ->get();

                $present = $reviews->where('classification', 'present');
                $attended = $reviews->whereIn('classification', ['present', 'half_day']);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'position' => $user->division?->name ?? '-',
                    'reviewed_days' => $reviews->count(),
                    'expected_days' => $expectedDays,
                    'hadir' => $present->count(),
                    'telat' => $attended->where('late_minutes', '>', 0)->count(),
                    'telat_menit' => $attended->sum('late_minutes'),
                    'setengah_hari' => $reviews->where('classification', 'half_day')->count(),
                    'alpha' => $reviews->where('classification', 'absent')->count(),
                    'izin' => $reviews->where('classification', 'permission')->count(),
                    'sakit' => $reviews->where('classification', 'sick')->count(),
                    'cuti' => $reviews->where('classification', 'leave')->count(),
                    'dinas' => $reviews->where('classification', 'business_trip')->count(),
                    'libur' => $reviews->whereIn('classification', ['rest_day', 'holiday'])->count(),
                    'perlu_konfirmasi' => $reviews->where('classification', 'unresolved')->count(),
                ];
            });

        return Inertia::render('hrd/attendance-range-summary', [
            'dari' => $dari,
            'sampai' => $sampai,
            'employees' => $employees,
            'confirmations' => AttendanceReview::where('company_id', $request->user()->company_id)
                ->whereBetween('date', [$dari, $sampai])->whereNotNull('clock_in_at')
                ->whereNull('clock_out_at')->where('classification', 'unresolved')
                ->with('user:id,name')->orderBy('date')->get()->map(fn (AttendanceReview $review) => [
                    'id' => $review->id, 'user_id' => $review->user_id, 'name' => $review->user->name,
                    'date' => $review->date->toDateString(), 'version' => $review->version,
                    'clock_in' => $review->clock_in_at?->format('H:i'),
                    'can_confirm' => $review->classification === 'unresolved',
                ]),
        ]);
    }
}
