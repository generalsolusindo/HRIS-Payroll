<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = Validator::make($request->only('bulan'), [
            'bulan' => ['nullable', 'date_format:Y-m'],
        ])->valid();

        $month = isset($validated['bulan'])
            ? Carbon::createFromFormat('Y-m', $validated['bulan'])
            : today();

        $attendances = $request->user()->attendances()
            ->whereYear('tanggal', $month->year)
            ->whereMonth('tanggal', $month->month)
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn (Attendance $attendance) => [
                'tanggal' => $attendance->tanggal->toDateString(),
                'clock_in_time' => $attendance->clock_in_time,
                'clock_in_status' => $attendance->clock_in_status,
                'clock_in_late_minutes' => $attendance->clock_in_late_minutes,
                'clock_in_photo_url' => $attendance->clockInPhotoUrl(),
                'clock_out_time' => $attendance->clock_out_time,
                'clock_out_status' => $attendance->clock_out_status,
                'clock_out_early_minutes' => $attendance->clock_out_early_minutes,
                'clock_out_photo_url' => $attendance->clockOutPhotoUrl(),
            ]);

        return Inertia::render('attendance-history', [
            'bulan' => $month->format('Y-m'),
            'attendances' => $attendances,
        ]);
    }
}
