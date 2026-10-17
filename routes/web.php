<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaceVerificationController;
use App\Http\Controllers\Finance\PaymentProofController;
use App\Http\Controllers\Finance\PayrollPaymentController;
use App\Http\Controllers\Hrd\AttendanceConfirmationController;
use App\Http\Controllers\Hrd\AttendanceDashboardController;
use App\Http\Controllers\Hrd\AttendanceRangeSummaryController;
use App\Http\Controllers\Hrd\CompanyCalendarDayController;
use App\Http\Controllers\Hrd\CompanySettingsController;
use App\Http\Controllers\Hrd\DivisionController;
use App\Http\Controllers\Hrd\LeaveRequestApprovalController;
use App\Http\Controllers\Hrd\OvertimeApprovalController;
use App\Http\Controllers\Hrd\PayrollAdjustmentController;
use App\Http\Controllers\Hrd\PayrollAuditLogController;
use App\Http\Controllers\Hrd\PayrollComponentController;
use App\Http\Controllers\Hrd\PayrollPayslipController;
use App\Http\Controllers\Hrd\PayrollPeriodController;
use App\Http\Controllers\Hrd\PayrollPolicyController;
use App\Http\Controllers\Hrd\PayrollProfileController;
use App\Http\Controllers\Hrd\PayrollRunController;
use App\Http\Controllers\Hrd\UserManagementController;
use App\Http\Controllers\LeaveRequestAttachmentController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\Manager\LeaveRequestApprovalController as ManagerLeaveRequestApprovalController;
use App\Http\Controllers\Manager\PayrollRunApprovalController;
use App\Http\Controllers\OvertimeRequestController;
use App\Http\Controllers\PenggajianController;
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
    Route::get('dashboard', DashboardController::class)->middleware('face.enrolled')->name('dashboard');

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

    Route::get('penggajian', [PenggajianController::class, 'index'])->name('penggajian');
    Route::get('penggajian/pratinjau/{entry}', [PenggajianController::class, 'pratinjau'])->name('penggajian.pratinjau');
    Route::get('penggajian/{payslip}', [PenggajianController::class, 'show'])->name('penggajian.show');
    Route::get('daily-report', fn () => Inertia::render('daily-report'))->name('daily-report');
    Route::get('izin', [LeaveRequestController::class, 'index'])->name('izin');
    Route::post('izin', [LeaveRequestController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('izin.store');
    // Registered before izin/{leaveRequest} so "attachments" isn't
    // swallowed as a {leaveRequest} parameter.
    Route::get('izin/attachments/{attachment}', [LeaveRequestAttachmentController::class, 'show'])->name('izin.attachments.show');
    Route::get('izin/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('izin.show');

    Route::get('lembur', [OvertimeRequestController::class, 'index'])->name('lembur.index');
    Route::post('lembur', [OvertimeRequestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('lembur.store');
});

Route::middleware(['auth', 'active', 'role:hrd'])->prefix('hrd')->name('hrd.')->group(function () {
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    Route::get('absensi', [AttendanceDashboardController::class, 'index'])->name('absensi.index');
    Route::patch('konfirmasi-absensi/{review}', [AttendanceConfirmationController::class, 'update'])->name('konfirmasi-absensi.update');
    Route::get('rekap-periode', [AttendanceRangeSummaryController::class, 'index'])->name('rekap-periode.index');

    Route::get('izin', [LeaveRequestApprovalController::class, 'index'])->name('izin.index');
    // Registered before izin/{leaveRequest}-style routes so these literal
    // segments are never swallowed as a route parameter.
    Route::get('izin/riwayat', [LeaveRequestApprovalController::class, 'riwayat'])->name('izin.riwayat');
    Route::get('izin/laporan', [LeaveRequestApprovalController::class, 'laporan'])->name('izin.laporan');
    Route::patch('izin/{leaveRequest}/approve', [LeaveRequestApprovalController::class, 'approve'])->name('izin.approve');
    Route::patch('izin/{leaveRequest}/reject', [LeaveRequestApprovalController::class, 'reject'])->name('izin.reject');

    Route::get('divisi', [DivisionController::class, 'index'])->name('divisi.index');
    Route::post('divisi', [DivisionController::class, 'store'])->name('divisi.store');
    Route::put('divisi/{division}', [DivisionController::class, 'update'])->name('divisi.update');
    Route::delete('divisi/{division}', [DivisionController::class, 'destroy'])->name('divisi.destroy');

    Route::get('komponen-gaji', [PayrollComponentController::class, 'index'])->name('komponen-gaji.index');
    Route::post('komponen-gaji', [PayrollComponentController::class, 'store'])->name('komponen-gaji.store');
    Route::put('komponen-gaji/{payroll_component}', [PayrollComponentController::class, 'update'])->name('komponen-gaji.update');
    Route::delete('komponen-gaji/{payroll_component}', [PayrollComponentController::class, 'destroy'])->name('komponen-gaji.destroy');

    Route::get('profil-gaji', [PayrollProfileController::class, 'index'])->name('profil-gaji.index');
    Route::get('profil-gaji/{user}/edit', [PayrollProfileController::class, 'edit'])->name('profil-gaji.edit');
    Route::put('profil-gaji/{user}', [PayrollProfileController::class, 'update'])->name('profil-gaji.update');

    Route::get('periode-gaji', [PayrollPeriodController::class, 'index'])->name('periode-gaji.index');
    Route::post('periode-gaji', [PayrollPeriodController::class, 'store'])->name('periode-gaji.store');
    Route::delete('periode-gaji/{period}', [PayrollPeriodController::class, 'destroy'])->name('periode-gaji.destroy');
    Route::post('periode-gaji/{period}/minta-hapus', [PayrollPeriodController::class, 'requestDeletion'])->name('periode-gaji.minta-hapus');
    Route::post('periode-gaji/{period}/perbarui-draft', [PayrollRunController::class, 'refresh'])->name('periode-gaji.perbarui-draft');
    Route::post('periode-gaji/{period}/hitung', [PayrollRunController::class, 'store'])->name('periode-gaji.hitung');

    Route::get('hasil-payroll/{run}', [PayrollRunController::class, 'show'])->name('hasil-payroll.show');
    Route::patch('hasil-payroll/{run}/ajukan', [PayrollRunController::class, 'submit'])->name('hasil-payroll.ajukan');
    Route::patch('hasil-payroll/{run}/batalkan', [PayrollRunController::class, 'cancel'])->name('hasil-payroll.batalkan');
    Route::patch('hasil-payroll/{run}/bagikan', [PayrollRunController::class, 'share'])->name('hasil-payroll.bagikan');
    Route::post('hasil-payroll/entri/{entry}/penyesuaian', [PayrollAdjustmentController::class, 'store'])->name('hasil-payroll.penyesuaian.store');
    Route::delete('hasil-payroll/penyesuaian/{line}', [PayrollAdjustmentController::class, 'destroy'])->name('hasil-payroll.penyesuaian.destroy');

    Route::post('hasil-payroll/entri/{entry}/slip', [PayrollPayslipController::class, 'store'])->name('hasil-payroll.entri.slip');

    Route::get('kebijakan-absensi', [PayrollPolicyController::class, 'index'])->name('kebijakan-absensi.index');
    Route::put('kebijakan-absensi/{policy}', [PayrollPolicyController::class, 'update'])->name('kebijakan-absensi.update');

    Route::get('kalender-kerja', [CompanyCalendarDayController::class, 'index'])->name('kalender-kerja.index');
    Route::post('kalender-kerja', [CompanyCalendarDayController::class, 'store'])->name('kalender-kerja.store');
    Route::delete('kalender-kerja/{day}', [CompanyCalendarDayController::class, 'destroy'])->name('kalender-kerja.destroy');

    Route::get('persetujuan-lembur', [OvertimeApprovalController::class, 'index'])->name('persetujuan-lembur.index');
    Route::patch('persetujuan-lembur/{overtimeRequest}/approve', [OvertimeApprovalController::class, 'approve'])->name('persetujuan-lembur.approve');
    Route::patch('persetujuan-lembur/{overtimeRequest}/reject', [OvertimeApprovalController::class, 'reject'])->name('persetujuan-lembur.reject');

    Route::get('audit-log', [PayrollAuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('pengaturan-perusahaan', [CompanySettingsController::class, 'index'])->name('pengaturan-perusahaan.index');
    Route::put('pengaturan-perusahaan/{company}', [CompanySettingsController::class, 'update'])->name('pengaturan-perusahaan.update');
});

Route::middleware(['auth', 'active', 'role:admin'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('izin', [ManagerLeaveRequestApprovalController::class, 'index'])->name('izin.index');
    Route::get('izin/riwayat', [ManagerLeaveRequestApprovalController::class, 'riwayat'])->name('izin.riwayat');
    Route::get('izin/laporan', [ManagerLeaveRequestApprovalController::class, 'laporan'])->name('izin.laporan');
    Route::patch('izin/{leaveRequest}/approve', [ManagerLeaveRequestApprovalController::class, 'approve'])->name('izin.approve');
    Route::patch('izin/{leaveRequest}/reject', [ManagerLeaveRequestApprovalController::class, 'reject'])->name('izin.reject');

    Route::get('payroll', [PayrollRunApprovalController::class, 'index'])->name('payroll.index');
    Route::get('payroll/{run}', [PayrollRunApprovalController::class, 'show'])->name('payroll.show');
    Route::patch('payroll/{run}/approve', [PayrollRunApprovalController::class, 'approve'])->name('payroll.approve');
    Route::patch('payroll/{run}/revisi', [PayrollRunApprovalController::class, 'requestRevision'])->name('payroll.revisi');
    Route::patch('payroll/hapus-periode/{deletionRequest}/approve', [PayrollRunApprovalController::class, 'approvePeriodDeletion'])->name('payroll.hapus-periode.approve');
    Route::patch('payroll/hapus-periode/{deletionRequest}/reject', [PayrollRunApprovalController::class, 'rejectPeriodDeletion'])->name('payroll.hapus-periode.reject');
});

Route::middleware(['auth', 'active', 'role:finance'])->prefix('finance')->name('finance.')->group(function () {
    Route::get('payroll', [PayrollPaymentController::class, 'index'])->name('payroll.index');
    Route::get('payroll/{run}', [PayrollPaymentController::class, 'show'])->name('payroll.show');
    Route::patch('payroll/{run}/kunci', [PayrollPaymentController::class, 'lock'])->name('payroll.kunci');
    Route::post('entri/{entry}/bayar', [PayrollPaymentController::class, 'storePayment'])->name('entri.bayar');
    Route::patch('pembayaran/{payment}/batalkan', [PayrollPaymentController::class, 'voidPayment'])->name('pembayaran.batalkan');
    Route::get('pembayaran/{payment}/bukti', [PaymentProofController::class, 'show'])->name('pembayaran.bukti');
});

require __DIR__.'/settings.php';
