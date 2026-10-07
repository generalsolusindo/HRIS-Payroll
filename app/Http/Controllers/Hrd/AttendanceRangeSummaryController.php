<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\AttendanceReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRangeSummaryController extends Controller
{
    /**
     * Aggregates already-reviewed attendance_reviews rows (the same
     * classification HRD validates in Koreksi Absensi, and the same data
     * payroll reads) over a date range — this never generates or changes
     * reviews itself, so a day nobody has reviewed yet simply doesn't count
     * toward any bucket below instead of being guessed from raw punches.
     */
    public function index(Request $request): Response
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

        $employees = User::where('company_id', $request->user()->company_id)
            ->where('role', 'karyawan')->where('status', 'active')
            ->with('position:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'position_id'])
            ->map(function (User $user) use ($dari, $sampai, $expectedDays) {
                $reviews = AttendanceReview::where('user_id', $user->id)
                    ->whereBetween('date', [$dari, $sampai])
                    ->get();

                $present = $reviews->where('classification', 'present');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'position' => $user->position->name,
                    'reviewed_days' => $reviews->count(),
                    'expected_days' => $expectedDays,
                    'hadir' => $present->count(),
                    'telat' => $present->where('late_minutes', '>', 0)->count(),
                    'telat_menit' => $present->sum('late_minutes'),
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
        ]);
    }
}
