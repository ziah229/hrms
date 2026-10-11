<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\ComponentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OvertimeRequestController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    // 2. Data Karyawan
    Route::controller(EmployeeController::class)->prefix('employees')->name('employees.')->group(function () {
        Route::get('/', 'index')->middleware('permission:employee.view')->name('index');
        Route::get('/export', 'export')->middleware('permission:employee.export')->name('export');
        Route::post('/import', 'import')->middleware('permission:employee.create')->name('import');
        Route::post('/', 'store')->middleware('permission:employee.create')->name('store');
        Route::get('/{employee}', 'show')->middleware('permission:employee.view')->name('show');
        Route::put('/{employee}', 'update')->middleware('permission:employee.update')->name('update');
        Route::post('/{employee}/resign', 'resign')->middleware('permission:employee.update')->name('resign');
        Route::delete('/{employee}', 'destroy')->middleware('permission:employee.delete')->name('destroy');
    });

    // Master data (departemen, jabatan, lokasi, shift, jenis cuti, hari libur) — permission dicek per tipe.
    Route::controller(MasterDataController::class)->prefix('master/{type}')->name('master.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });

    // 3. Absensi, cuti & lembur
    Route::controller(AttendanceController::class)->prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', 'index')->middleware('permission:attendance.view')->name('index');
        Route::get('/export', 'export')->middleware('permission:attendance.export')->name('export');
        Route::post('/import', 'import')->middleware('permission:attendance.create')->name('import');
        Route::post('/', 'store')->middleware('permission:attendance.create')->name('store');
        Route::put('/{attendance}', 'update')->middleware('permission:attendance.update')->name('update');
    });
    Route::controller(LeaveRequestController::class)->prefix('leave-requests')->name('leave-requests.')->group(function () {
        Route::get('/', 'index')->middleware('permission:attendance.view')->name('index');
        Route::post('/', 'store')->middleware('permission:attendance.request,attendance.create')->name('store');
        Route::post('/{leaveRequest}/approve', 'approve')->middleware('permission:attendance.approve')->name('approve');
        Route::post('/{leaveRequest}/reject', 'reject')->middleware('permission:attendance.approve')->name('reject');
    });
    Route::controller(OvertimeRequestController::class)->prefix('overtime-requests')->name('overtime-requests.')->group(function () {
        Route::get('/', 'index')->middleware('permission:attendance.view')->name('index');
        Route::post('/', 'store')->middleware('permission:attendance.request,attendance.create')->name('store');
        Route::post('/{overtimeRequest}/approve', 'approve')->middleware('permission:attendance.approve')->name('approve');
        Route::post('/{overtimeRequest}/reject', 'reject')->middleware('permission:attendance.approve')->name('reject');
    });

    // 4. Data Gaji
    Route::controller(SalaryController::class)->prefix('salaries')->name('salaries.')->group(function () {
        Route::get('/', 'index')->middleware('permission:salary.view')->name('index');
        Route::get('/export', 'export')->middleware('permission:salary.export')->name('export');
        Route::post('/', 'store')->middleware('permission:salary.create,salary.update')->name('store');
        Route::get('/{employee}/history', 'history')->middleware('permission:salary.view')->name('history');
    });

    // 5. Allowances & Deductions
    Route::controller(ComponentController::class)->prefix('components')->name('components.')->group(function () {
        Route::get('/', 'index')->middleware('permission:component.view')->name('index');
        Route::post('/', 'store')->middleware('permission:component.create')->name('store');
        Route::put('/{payComponent}', 'update')->middleware('permission:component.update')->name('update');
        Route::delete('/{payComponent}', 'destroy')->middleware('permission:component.delete')->name('destroy');

        Route::get('/employee', 'employeeIndex')->middleware('permission:component.view')->name('employee.index');
        Route::post('/employee', 'employeeStore')->middleware('permission:component.create')->name('employee.store');
        Route::put('/employee/{employeeComponent}', 'employeeUpdate')->middleware('permission:component.update')->name('employee.update');
        Route::delete('/employee/{employeeComponent}', 'employeeDestroy')->middleware('permission:component.delete')->name('employee.destroy');

        Route::get('/adhoc', 'adhocIndex')->middleware('permission:component.view')->name('adhoc.index');
        Route::post('/adhoc', 'adhocStore')->middleware('permission:component.create')->name('adhoc.store');
        Route::delete('/adhoc/{adhocComponent}', 'adhocDestroy')->middleware('permission:component.delete')->name('adhoc.destroy');

        Route::get('/loans', 'loanIndex')->middleware('permission:component.view')->name('loans.index');
        Route::post('/loans', 'loanStore')->middleware('permission:component.create')->name('loans.store');
    });

    // 6. Register Gaji
    Route::controller(PayrollController::class)->prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', 'index')->middleware('permission:payroll.view')->name('index');
        Route::post('/periods', 'storePeriod')->middleware('permission:payroll.process')->name('periods.store');
        Route::get('/registers/{register}', 'showRegister')->middleware('permission:payroll.view')->name('registers.show');
        Route::get('/{period}/registers', 'registers')->middleware('permission:payroll.view')->name('registers');
        Route::post('/{period}/process', 'process')->middleware('permission:payroll.process')->name('process');
        Route::post('/{period}/approve', 'approve')->middleware('permission:payroll.approve')->name('approve');
        Route::post('/{period}/finalize', 'finalize')->middleware('permission:payroll.finalize')->name('finalize');
        Route::post('/{period}/reopen', 'reopen')->middleware('permission:payroll.reopen')->name('reopen');
        Route::get('/{period}/export/{format}', 'export')->middleware('permission:payroll.export')->whereIn('format', ['xlsx', 'pdf'])->name('export');
    });

    // 7. Payslip
    Route::controller(PayslipController::class)->name('payslips.')->group(function () {
        Route::get('/payslips', 'index')->middleware('permission:payslip.view')->name('index');
        Route::post('/payslips/send-bulk', 'sendBulk')->middleware('permission:payslip.send')->name('send-bulk');
        Route::get('/my-payslips', 'mine')->middleware('permission:payslip.view_own')->name('mine');
        Route::get('/payslips/{payslip}/preview', 'preview')->middleware('permission:payslip.view,payslip.view_own')->name('preview');
        Route::get('/payslips/{payslip}/pdf', 'pdf')->middleware('permission:payslip.export,payslip.view_own')->name('pdf');
        Route::post('/payslips/{payslip}/send', 'send')->middleware('permission:payslip.send')->name('send');
    });

    // 8. Report
    Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'index')->middleware('permission:report.view')->name('index');
        Route::get('/{code}/preview', 'preview')->middleware('permission:report.view')->name('preview');
        Route::get('/{code}/export', 'export')->middleware('permission:report.export')->name('export');
    });

    // 9. Pengaturan
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::middleware('permission:user.manage')->controller(UserController::class)->group(function () {
            Route::get('/users', 'index')->name('users.index');
            Route::post('/users', 'store')->name('users.store');
            Route::put('/users/{user}', 'update')->name('users.update');
            Route::delete('/users/{user}', 'destroy')->name('users.destroy');
        });
        Route::middleware('permission:role.manage')->controller(RoleController::class)->group(function () {
            Route::get('/roles', 'index')->name('roles.index');
            Route::post('/roles', 'store')->name('roles.store');
            Route::put('/roles/{role}', 'update')->name('roles.update');
            Route::delete('/roles/{role}', 'destroy')->name('roles.destroy');
        });
        Route::middleware('permission:setting.manage')->group(function () {
            Route::get('/company', [CompanySettingController::class, 'edit'])->name('company.edit');
            Route::put('/company', [CompanySettingController::class, 'update'])->name('company.update');
        });
        Route::get('/audit-logs', AuditLogController::class)->middleware('permission:audit.view')->name('audit-logs');
    });
});
