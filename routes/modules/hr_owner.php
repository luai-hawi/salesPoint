<?php

use App\Http\Controllers\ShopOwner\AttendanceController;
use App\Http\Controllers\ShopOwner\AttendanceLocationController;
use App\Http\Controllers\ShopOwner\AttendanceSettingsController;
use App\Http\Controllers\ShopOwner\EmployeeController;
use App\Http\Controllers\ShopOwner\PayrollController;
use Illuminate\Support\Facades\Route;

Route::prefix('shopowner')
    ->as('shopowner.')
    ->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', 'tier.feature:hr'])
    ->group(function () {
        Route::middleware([\App\Http\Middleware\PermissionMiddleware::class . ':manage_employees'])->group(function () {
            Route::delete('employees/{employee}/devices/{device}', [EmployeeController::class, 'revokeDevice'])->whereNumber(['employee', 'device'])->name('employees.devices.destroy');
            Route::delete('employees/{employee}/credentials/{credential}', [EmployeeController::class, 'destroyCredential'])->whereNumber(['employee', 'credential'])->name('employees.credentials.destroy');

            Route::get('attendance/settings', [AttendanceSettingsController::class, 'edit'])->name('attendance.settings');
            Route::put('attendance/settings', [AttendanceSettingsController::class, 'update'])->name('attendance.settings.update');
            Route::post('attendance/settings/regenerate-portal', [AttendanceSettingsController::class, 'regeneratePortalKey'])->name('attendance.settings.regenerate-portal');
            Route::post('attendance/locations', [AttendanceLocationController::class, 'store'])->name('attendance.locations.store');
            Route::put('attendance/locations/{location}', [AttendanceLocationController::class, 'update'])->whereNumber('location')->name('attendance.locations.update');
            Route::delete('attendance/locations/{location}', [AttendanceLocationController::class, 'destroy'])->whereNumber('location')->name('attendance.locations.destroy');

            Route::get('attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
            Route::get('attendance/timesheet/{employee}', [AttendanceController::class, 'timesheet'])->whereNumber('employee')->name('attendance.timesheet');
            Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
            Route::put('attendance/{attendanceRecord}', [AttendanceController::class, 'update'])->whereNumber('attendanceRecord')->name('attendance.update');
            Route::delete('attendance/{attendanceRecord}', [AttendanceController::class, 'destroy'])->whereNumber('attendanceRecord')->name('attendance.destroy');
            Route::post('attendance/{attendanceRecord}/review', [AttendanceController::class, 'review'])->whereNumber('attendanceRecord')->name('attendance.review');

            Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
            Route::get('payroll/export', [PayrollController::class, 'export'])->name('payroll.export');
            Route::post('payroll/{employee}/pay', [PayrollController::class, 'pay'])->whereNumber('employee')->name('payroll.pay');
            Route::get('payroll/{employee}/payslip', [PayrollController::class, 'payslip'])->whereNumber('employee')->name('payroll.payslip');
            Route::post('payroll/{employee}/adjustments', [PayrollController::class, 'storeAdjustment'])->whereNumber('employee')->name('payroll.adjustments.store');
            Route::delete('payroll/adjustments/{adjustment}', [PayrollController::class, 'destroyAdjustment'])->whereNumber('adjustment')->name('payroll.adjustments.destroy');
            Route::post('payroll/{employee}/leaves', [PayrollController::class, 'storeLeave'])->whereNumber('employee')->name('payroll.leaves.store');
            Route::delete('payroll/leaves/{leave}', [PayrollController::class, 'destroyLeave'])->whereNumber('leave')->name('payroll.leaves.destroy');
        });
    });
