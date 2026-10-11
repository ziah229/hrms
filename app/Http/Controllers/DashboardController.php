<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollRegister;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = now()->toDateString();

        // Role dengan scope "self" mendapat dashboard ringkas.
        if (($user->role?->data_scope ?? 'self') === 'self') {
            return $this->personal($user->employee);
        }

        $visible = Employee::visibleTo($user);
        $ids = (clone $visible)->pluck('id');

        $activeCount = (clone $visible)->where('is_active', true)->count();
        $newCount = (clone $visible)->whereBetween('join_date', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $resignCount = (clone $visible)->whereBetween('resign_date', [now()->startOfMonth(), now()->endOfMonth()])->count();

        $todayStats = Attendance::whereIn('employee_id', $ids)->whereDate('attendance_date', $today)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $presentToday = ($todayStats['hadir'] ?? 0) + ($todayStats['terlambat'] ?? 0) + ($todayStats['dinas'] ?? 0);

        // Tren 5 hari kerja terakhir.
        $trend = Attendance::whereIn('employee_id', $ids)
            ->where('attendance_date', '>=', now()->subDays(10)->toDateString())
            ->selectRaw("attendance_date, SUM(status IN ('hadir','dinas')) as ontime, SUM(status = 'terlambat') as late")
            ->groupBy('attendance_date')->orderByDesc('attendance_date')->limit(5)->get()->reverse()->values();
        $monthAtt = Attendance::whereIn('employee_id', $ids)->where('attendance_date', '>=', now()->startOfMonth()->subMonth()->toDateString())
            ->selectRaw("COUNT(*) as total, SUM(status IN ('hadir','dinas')) as ontime, SUM(status = 'terlambat') as late, SUM(status = 'alpha') as alpha")
            ->first();

        $lastPeriod = PayrollPeriod::where('status', 'final')->latest('end_date')->first();
        $payrollCost = $lastPeriod && $user->hasPermission('payroll.view', 'salary.view')
            ? PayrollRegister::where('payroll_period_id', $lastPeriod->id)->sum('net_salary')
            : null;
        $openPeriod = PayrollPeriod::whereIn('status', ['processed', 'approved'])->latest('end_date')->first();

        $expiringContracts = EmployeeContract::with('employee')->whereIn('employee_id', $ids)
            ->whereBetween('end_date', [$today, now()->addDays(30)->toDateString()])->orderBy('end_date')->get();

        $composition = (clone $visible)->where('is_active', true)
            ->selectRaw('employment_status, count(*) as total')->groupBy('employment_status')->pluck('total', 'employment_status');

        $pendingLeaves = LeaveRequest::with(['employee.department', 'leaveType'])->whereIn('employee_id', $ids)->where('status', 'pending')->latest()->limit(5)->get();
        $pendingOvertimes = OvertimeRequest::with('employee.department')->whereIn('employee_id', $ids)->where('status', 'pending')->latest()->limit(5)->get();

        return view('dashboard', [
            'activeCount' => $activeCount,
            'newCount' => $newCount,
            'resignCount' => $resignCount,
            'todayStats' => $todayStats,
            'presentToday' => $presentToday,
            'trend' => $trend,
            'monthAtt' => $monthAtt,
            'lastPeriod' => $lastPeriod,
            'payrollCost' => $payrollCost,
            'openPeriod' => $openPeriod,
            'expiringContracts' => $expiringContracts,
            'composition' => $composition,
            'pendingLeaves' => $pendingLeaves,
            'pendingOvertimes' => $pendingOvertimes,
            'holidays' => Holiday::where('holiday_date', '>=', $today)->orderBy('holiday_date')->limit(3)->get(),
            'lastActivity' => AuditLog::with('user')->whereNot('action', 'like', 'auth.%')->latest('id')->first(),
        ]);
    }

    protected function personal(?Employee $employee): View
    {
        $attendance = $employee
            ? Attendance::where('employee_id', $employee->id)->where('attendance_date', '>=', now()->startOfMonth()->toDateString())
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $latestRegister = $employee
            ? PayrollRegister::with(['period', 'payslip'])->where('employee_id', $employee->id)
                ->whereHas('period', fn ($q) => $q->where('status', 'final'))->latest('id')->first()
            : null;

        return view('dashboard-personal', [
            'employee' => $employee?->load(['department', 'position']),
            'attendance' => $attendance,
            'latestRegister' => $latestRegister,
            'leaves' => $employee ? LeaveRequest::with('leaveType')->where('employee_id', $employee->id)->latest()->limit(5)->get() : collect(),
            'holidays' => Holiday::where('holiday_date', '>=', now()->toDateString())->orderBy('holiday_date')->limit(3)->get(),
        ]);
    }
}
