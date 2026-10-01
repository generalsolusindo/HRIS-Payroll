<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceHistoryController;
use App\Http\Controllers\Hrd\AttendanceDashboardController;
use App\Http\Controllers\Hrd\EmployeeApprovalController;
use App\Http\Controllers\LeaveRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('pending-approval', fn () => Inertia::render('auth/pending-approval', [
        'status' => auth()->user()->status,
    ]))->name('pending-approval');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', function () {
        $attendance = auth()->user()->attendances()->where('tanggal', today())->first();

        return Inertia::render('dashboard', [
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
        ]);
    })->name('dashboard');

    Route::post('absen/masuk', [AttendanceController::class, 'clockIn'])->name('absen.masuk');
    Route::post('absen/pulang', [AttendanceController::class, 'clockOut'])->name('absen.pulang');

    Route::get('riwayat-absen', [AttendanceHistoryController::class, 'index'])->name('riwayat-absen.index');

    Route::get('penggajian', fn () => Inertia::render('penggajian'))->name('penggajian');
    Route::get('daily-report', fn () => Inertia::render('daily-report'))->name('daily-report');
    Route::get('izin', fn () => Inertia::render('izin'))->name('izin');
    Route::post('izin', [LeaveRequestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('izin.store');
});

Route::middleware(['auth', 'active', 'role:hrd'])->prefix('hrd')->name('hrd.')->group(function () {
    Route::get('karyawan', [EmployeeApprovalController::class, 'index'])->name('karyawan.index');
    Route::patch('karyawan/{user}/approve', [EmployeeApprovalController::class, 'approve'])->name('karyawan.approve');
    Route::patch('karyawan/{user}/reject', [EmployeeApprovalController::class, 'reject'])->name('karyawan.reject');

    Route::get('absensi', [AttendanceDashboardController::class, 'index'])->name('absensi.index');
});

require __DIR__.'/settings.php';
