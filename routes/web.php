<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceHistoryController;
use App\Http\Controllers\FaceVerificationController;
use App\Http\Controllers\Hrd\AttendanceDashboardController;
use App\Http\Controllers\Hrd\LeaveRequestApprovalController;
use App\Http\Controllers\Hrd\UserManagementController;
use App\Http\Controllers\LeaveRequestAttachmentController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\Manager\LeaveRequestApprovalController as ManagerLeaveRequestApprovalController;
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
        $user = auth()->user();
        $attendance = $user->attendances()->where('tanggal', today())->first();
        $workSchedule = $user->company->workSchedules()->first();

        return Inertia::render('dashboard', [
            'user' => [
                'name' => $user->name,
            ],
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
    })->middleware('face.enrolled')->name('dashboard');

    Route::post('absen/masuk', [AttendanceController::class, 'clockIn'])->middleware('face.enrolled')->name('absen.masuk');
    Route::post('absen/pulang', [AttendanceController::class, 'clockOut'])->middleware('face.enrolled')->name('absen.pulang');

    // Live preview only — never the authoritative check, see
    // FaceVerificationController's docblock. Throttle is generous enough
    // for ~1 request/1.5s of normal polling (≈40/min) while still
    // capping abuse; the worker's own bounded queue is the second layer
    // of capacity protection.
    Route::post('absen/face-verification', [FaceVerificationController::class, 'check'])
        ->middleware(['face.enrolled', 'throttle:40,1'])
        ->name('absen.face-verification');

    Route::get('riwayat-absen', [AttendanceHistoryController::class, 'index'])->name('riwayat-absen.index');

    Route::get('penggajian', fn () => Inertia::render('penggajian'))->name('penggajian');
    Route::get('daily-report', fn () => Inertia::render('daily-report'))->name('daily-report');
    Route::get('izin', [LeaveRequestController::class, 'index'])->name('izin');
    Route::post('izin', [LeaveRequestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('izin.store');
    // Registered before izin/{leaveRequest} so "attachments" isn't
    // swallowed as a {leaveRequest} parameter.
    Route::get('izin/attachments/{attachment}', [LeaveRequestAttachmentController::class, 'show'])->name('izin.attachments.show');
    Route::get('izin/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('izin.show');
});

Route::middleware(['auth', 'active', 'role:hrd'])->prefix('hrd')->name('hrd.')->group(function () {
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    Route::get('absensi', [AttendanceDashboardController::class, 'index'])->name('absensi.index');

    Route::get('izin', [LeaveRequestApprovalController::class, 'index'])->name('izin.index');
    Route::patch('izin/{leaveRequest}/approve', [LeaveRequestApprovalController::class, 'approve'])->name('izin.approve');
    Route::patch('izin/{leaveRequest}/reject', [LeaveRequestApprovalController::class, 'reject'])->name('izin.reject');
});

Route::middleware(['auth', 'active', 'role:admin'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('izin', [ManagerLeaveRequestApprovalController::class, 'index'])->name('izin.index');
    Route::patch('izin/{leaveRequest}/approve', [ManagerLeaveRequestApprovalController::class, 'approve'])->name('izin.approve');
    Route::patch('izin/{leaveRequest}/reject', [ManagerLeaveRequestApprovalController::class, 'reject'])->name('izin.reject');
});

require __DIR__.'/settings.php';
