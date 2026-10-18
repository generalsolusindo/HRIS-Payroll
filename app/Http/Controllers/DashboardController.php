<?php

namespace App\Http\Controllers;

use App\Models\CompanyCalendarDay;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriodDeletionRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\CompanyWorkCalendar;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        return match ($user->role) {
            'hrd' => $this->hrdDashboard($user),
            'admin' => $this->managerDashboard($user),
            'finance' => $this->financeDashboard($user),
            default => $this->employeeDashboard($user),
        };
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

    /**
     * Manager only approves things here — it never clocks in — so the
     * dashboard is a count of what's waiting on their decision instead of
     * the employee clock-in widget.
     */
    private function managerDashboard(User $user): Response
    {
        return Inertia::render('dashboard', [
            'user' => ['name' => $user->name],
            'managerSummary' => [
                'pending_leave' => LeaveRequest::where('status', 'pending_manager')
                    ->whereHas('user', fn ($query) => $query->where('company_id', $user->company_id))
                    ->count(),
                'pending_payroll' => PayrollRun::where('status', 'submitted')
                    ->whereHas('period', fn ($query) => $query->where('company_id', $user->company_id)->whereNull('archived_at'))
                    ->count(),
                'pending_period_deletion' => PayrollPeriodDeletionRequest::where('status', 'pending')
                    ->whereHas('period', fn ($query) => $query->where('company_id', $user->company_id))
                    ->count(),
            ],
        ]);
    }

    /**
     * Finance only records payments — same reasoning as Manager above.
     */
    private function financeDashboard(User $user): Response
    {
        return Inertia::render('dashboard', [
            'user' => ['name' => $user->name],
            'financeSummary' => [
                'pending_payment' => PayrollRun::whereIn('status', ['approved', 'locked', 'partially_paid'])
                    ->whereHas('period', fn ($query) => $query->where('company_id', $user->company_id))
                    ->count(),
            ],
        ]);
    }

    private function employeeDashboard(User $user): Response
    {
        $today = today();
        $attendance = $user->attendances()->where('tanggal', $today)->first();
        $workSchedule = $user->company->workSchedules()->first();
        $dayType = app(CompanyWorkCalendar::class)->dayType($user->company_id, $today, $user->id);
        $calendarDescription = CompanyCalendarDay::where('company_id', $user->company_id)
            ->whereDate('date', $today)
            ->value('description');

        // Collapses consecutive dates sharing a type+description into one
        // range (e.g. 5 individual Idul Fitri rows become one "31 Mar - 2
        // Apr" entry) so employees see it as a holiday stretch, not a list
        // of single days.
        $upcomingDaysOff = CompanyCalendarDay::where('company_id', $user->company_id)
            ->whereIn('type', ['holiday', 'rest_day'])
            ->whereDate('date', '>=', $today)
            ->orderBy('date')
            ->limit(90)
            ->get(['date', 'type', 'description'])
            ->reduce(function (array $ranges, CompanyCalendarDay $day) {
                $last = $ranges === [] ? null : $ranges[array_key_last($ranges)];
                if ($last !== null && $last['type'] === $day->type && $last['description'] === $day->description
                    && Carbon::parse($last['ends_on'])->addDay()->isSameDay($day->date)) {
                    $ranges[array_key_last($ranges)]['ends_on'] = $day->date->toDateString();
                } else {
                    $ranges[] = [
                        'type' => $day->type,
                        'description' => $day->description,
                        'starts_on' => $day->date->toDateString(),
                        'ends_on' => $day->date->toDateString(),
                    ];
                }

                return $ranges;
            }, []);

        return Inertia::render('dashboard', [
            'user' => [
                'name' => $user->name,
            ],
            'hrdSummary' => null,
            'isTeknisi' => $user->division?->name === 'Teknisi',
            'attendanceDay' => [
                'type' => $dayType,
                'label' => app(CompanyWorkCalendar::class)->label($dayType),
                'description' => $calendarDescription,
            ],
            'upcomingDaysOff' => $upcomingDaysOff,
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
