<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AdminExportController;
use App\Http\Controllers\AdminHolidayController;
use App\Http\Controllers\AdminLeaveRequestController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\PerformanceEntryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:pjlp')->group(function () {
        Route::post('/kinerja', [PerformanceEntryController::class, 'store'])->name('entries.store');
        Route::get('/laporan', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/absensi', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/absensi/{type}', [AttendanceController::class, 'create'])->name('attendance.create');
        Route::post('/absensi/{type}', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/cuti', [LeaveRequestController::class, 'index'])->name('leave.index');
        Route::get('/cuti/kalender', [LeaveRequestController::class, 'calendar'])->name('leave.calendar');
        Route::get('/cuti/ajukan', [LeaveRequestController::class, 'create'])->name('leave.create');
        Route::post('/cuti', [LeaveRequestController::class, 'store'])->name('leave.store');
        Route::get('/cuti/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave.show');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/absensi', [AdminAttendanceController::class, 'index'])->name('admin.attendance.index');
        Route::get('/admin/absensi/{attendanceRecord}', [AdminAttendanceController::class, 'show'])->name('admin.attendance.show');
        Route::get('/admin/cuti', [AdminLeaveRequestController::class, 'index'])->name('admin.leave.index');
        Route::get('/admin/cuti/kalender', [AdminLeaveRequestController::class, 'calendar'])->name('admin.leave.calendar');
        Route::get('/admin/cuti/export-excel', [AdminLeaveRequestController::class, 'exportExcel'])->name('admin.leave.exportExcel');
        Route::get('/admin/cuti/{leaveRequest}', [AdminLeaveRequestController::class, 'show'])->name('admin.leave.show');
        Route::put('/admin/cuti/{leaveRequest}/tanggal', [AdminLeaveRequestController::class, 'updateDates'])->name('admin.leave.updateDates');
        Route::post('/admin/cuti/{leaveRequest}/setujui', [AdminLeaveRequestController::class, 'approve'])->name('admin.leave.approve');
        Route::post('/admin/cuti/{leaveRequest}/tolak', [AdminLeaveRequestController::class, 'reject'])->name('admin.leave.reject');
        Route::get('/admin/cuti/{leaveRequest}/cetak', [AdminLeaveRequestController::class, 'print'])->name('admin.leave.print');
        Route::get('/admin/laporan/download-zip', [ReportController::class, 'downloadZip'])->name('admin.reports.downloadZip');
        Route::get('/admin/export-csv', [AdminExportController::class, 'csv'])->name('admin.export.csv');
        Route::get('/admin/holidays', [AdminHolidayController::class, 'index'])->name('admin.holidays.index');
        Route::post('/admin/holidays', [AdminHolidayController::class, 'store'])->name('admin.holidays.store');
        Route::post('/admin/holidays/sync-national', [AdminHolidayController::class, 'syncNational'])->name('admin.holidays.syncNational');
        Route::delete('/admin/holidays/{holiday}', [AdminHolidayController::class, 'destroy'])->name('admin.holidays.destroy');
        Route::get('/admin/laporan/{user}', [ReportController::class, 'show'])->name('admin.reports.show');
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/admin/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::get('/admin/users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
