<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        if ($user->role === 'hrd') {
            return $this->hrdDashboard($user);
        }

        return $this->employeeDashboard($user);
    }

    /**
     * HRD is a monitoring/management role — it never clocks in itself, so
     * the usual clock widget makes no sense here. This is a company-wide
     * (all companies — HRD isn't scoped to one, same as Rekap Absensi)
     * snapshot of today's attendance instead.
     */
    private function hrdDashboard(User $user): Response
    {
        $today = Carbon::today();
        $activeEmployees = User::where('role', 'karyawan')->where('status', 'active');

        $totalKaryawan = (clone $activeEmployees)->count();
        $sudahAbsen = (clone $activeEmployees)
            ->whereHas('attendances', fn ($query) => $query
                ->where('tanggal', $today)
                ->whereNotNull('clock_in_time'))
            ->count();
        $telat = (clone $activeEmployees)
            ->whereHas('attendances', fn ($query) => $query
                ->where('tanggal', $today)
                ->where('clock_in_status', 'telat'))
            ->count();

        return Inertia::render('dashboard', [
            'user' => ['name' => $user->name],
            'hrdSummary' => [
                'tanggal' => $today->toDateString(),
                'total_karyawan' => $totalKaryawan,
                'sudah_absen' => $sudahAbsen,
                'belum_absen' => $totalKaryawan - $sudahAbsen,
                'telat' => $telat,
                'pending_users' => User::where('status', 'pending')->count(),
            ],
        ]);
    }

    private function employeeDashboard(User $user): Response
    {
        $attendance = $user->attendances()->where('tanggal', today())->first();
        $workSchedule = $user->company->workSchedules()->first();

        return Inertia::render('dashboard', [
            'user' => [
                'name' => $user->name,
            ],
            'hrdSummary' => null,
            'isTeknisi' => $user->position?->name === 'Teknisi',
            'todayAttendance' => $attendance ? [
                'clock_in_time' => $attendance->clock_in_time,
                'clock_in_status' => $attendance->clock_in_status,
                'clock_in_late_minutes' => $attendance->clock_in_late_minutes,
                'clock_in_photo_url' => $attendance->clockInPhotoUrl(),
                'clock_out_time' => $attendance->clock_out_time,
                'clock_out_status' => $attendance->clock_out_status,
                'clock_out_early_minutes' => $attendance->clock_out_early_minutes,
                'clock_out_photo_url' => $attendance->clockOutPhotoUrl(),
            ] : null,
            // Display-only — the authoritative radius/accuracy check still
            // happens server-side in AttendanceController on submit.
            'officeLocations' => $user->company->officeLocations->map(fn ($office) => [
                'id' => $office->id,
                'name' => $office->name,
                'latitude' => (float) $office->latitude,
                'longitude' => (float) $office->longitude,
                'radius_meter' => $office->radius_meter,
            ]),
            'workSchedule' => $workSchedule ? [
                'name' => $workSchedule->name,
                'jam_masuk' => $workSchedule->jam_masuk,
                'jam_pulang' => $workSchedule->jam_pulang,
            ] : null,
        ]);
    }
}
