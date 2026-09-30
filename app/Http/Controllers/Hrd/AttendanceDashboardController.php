<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = Validator::make($request->only('tanggal'), [
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ])->valid();

        $date = isset($validated['tanggal'])
            ? Carbon::createFromFormat('Y-m-d', $validated['tanggal'])->startOfDay()
            : today();

        $employees = User::where('role', 'karyawan')
            ->where('status', 'active')
            ->with(['company:id,name', 'position:id,name'])
            ->with(['attendances' => fn ($query) => $query->where('tanggal', $date)])
            ->orderBy('name')
            ->get(['id', 'company_id', 'position_id', 'name'])
            ->map(fn (User $user) => $this->presentEmployee($user, $user->attendances->first()));

        return Inertia::render('hrd/attendance-dashboard', [
            'tanggal' => $date->toDateString(),
            'employees' => $employees,
        ]);
    }

    /**
     * @return array{id: int, name: string, company: string, position: string, clock_in_time: ?string, clock_in_status: ?string, clock_in_late_minutes: ?int, clock_in_photo_url: ?string, clock_out_time: ?string, clock_out_status: ?string, clock_out_early_minutes: ?int, clock_out_photo_url: ?string}
     */
    private function presentEmployee(User $user, ?Attendance $attendance): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'company' => $user->company->name,
            'position' => $user->position->name,
            'clock_in_time' => $attendance?->clock_in_time,
            'clock_in_status' => $attendance?->clock_in_status,
            'clock_in_late_minutes' => $attendance?->clock_in_late_minutes,
            'clock_in_photo_url' => $attendance?->clockInPhotoUrl(),
            'clock_out_time' => $attendance?->clock_out_time,
            'clock_out_status' => $attendance?->clock_out_status,
            'clock_out_early_minutes' => $attendance?->clock_out_early_minutes,
            'clock_out_photo_url' => $attendance?->clockOutPhotoUrl(),
        ];
    }
}
