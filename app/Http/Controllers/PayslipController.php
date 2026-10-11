<?php

namespace App\Http\Controllers;

use App\Jobs\SendPayslipEmail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\PayslipPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PayslipController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $periods = PayrollPeriod::where('status', 'final')->orderByDesc('year')->orderByDesc('month')->get();
        $period = $request->filled('period') ? $periods->firstWhere('id', $request->integer('period')) : $periods->first();

        $base = Payslip::whereHas('register', fn ($q) => $q->where('payroll_period_id', $period?->id));

        if ($request->expectsJson()) {
            $rows = (clone $base)->with('register.employee')
                ->when($request->filled('q'), fn ($q) => $q->whereHas('register.employee', fn ($e) => $e
                    ->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
                ->when($request->input('filter') === 'sent', fn ($q) => $q->whereNotNull('emailed_at'))
                ->when($request->input('filter') === 'unsent', fn ($q) => $q->whereNull('emailed_at'))
                ->when($request->input('filter') === 'viewed', fn ($q) => $q->whereNotNull('viewed_at'))
                ->orderBy('payslip_no')
                ->paginate($request->integer('per_page', 8))
                ->through(fn (Payslip $p) => [
                    'id' => $p->id,
                    'payslip_no' => $p->payslip_no,
                    'employee_no' => $p->register->snapshot['employee_no'] ?? '',
                    'full_name' => $p->register->snapshot['full_name'] ?? '',
                    'position' => $p->register->snapshot['position'] ?? '',
                    'department' => $p->register->snapshot['department'] ?? '',
                    'net_salary' => (float) $p->register->net_salary,
                    'emailed_at' => $p->emailed_at?->translatedFormat('d M Y, H:i'),
                    'viewed_at' => $p->viewed_at?->translatedFormat('d M Y, H:i'),
                ]);

            return response()->json($rows);
        }

        return view('payslips.index', [
            'periods' => $periods,
            'period' => $period,
            'stats' => [
                'total' => (clone $base)->count(),
                'sent' => (clone $base)->whereNotNull('emailed_at')->count(),
                'viewed' => (clone $base)->whereNotNull('viewed_at')->count(),
            ],
        ]);
    }

    public function mine(Request $request): View
    {
        $employee = $request->user()->employee;
        $payslips = Payslip::with('register.period')
            ->whereHas('register', fn ($q) => $q->where('employee_id', $employee?->id ?? 0))
            ->latest('generated_at')->get();

        return view('payslips.mine', ['payslips' => $payslips, 'selected' => $payslips->firstWhere('id', $request->integer('id')) ?? $payslips->first()]);
    }

    /**
     * Fragment HTML slip untuk panel preview (dimuat via AJAX).
     */
    public function preview(Request $request, Payslip $payslip): View
    {
        $this->authorizePayslip($request, $payslip);
        $payslip->load(['register.items', 'register.period', 'register.employee']);

        if ($this->isOwner($request, $payslip) && ! $payslip->viewed_at) {
            $payslip->update(['viewed_at' => now()]);
        }

        return view('payslips.partials.slip', ['payslip' => $payslip, 'company' => Company::first()]);
    }

    public function pdf(Request $request, Payslip $payslip, PayslipPdf $pdf): Response
    {
        $this->authorizePayslip($request, $payslip, 'payslip.export');
        AuditLog::record('payslip.download', $payslip);

        // Karyawan mengunduh miliknya sendiri tetap dengan password; admin dapat memilih tanpa password.
        $protect = $this->isOwner($request, $payslip) || ! $request->boolean('unprotected');

        return response($pdf->render($payslip, $protect), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$pdf->filename($payslip).'"',
        ]);
    }

    public function send(Payslip $payslip): JsonResponse
    {
        SendPayslipEmail::dispatch($payslip);
        AuditLog::record('payslip.send', $payslip);

        return response()->json(['message' => "Payslip {$payslip->payslip_no} masuk antrean pengiriman email."]);
    }

    public function sendBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'exists:payroll_periods,id'],
            'only_unsent' => ['boolean'],
        ]);

        $payslips = Payslip::whereHas('register', fn ($q) => $q->where('payroll_period_id', $data['period']))
            ->when($request->boolean('only_unsent', true), fn ($q) => $q->whereNull('emailed_at'))
            ->get();

        $payslips->each(fn ($p) => SendPayslipEmail::dispatch($p));
        AuditLog::record('payslip.send_bulk', PayrollPeriod::find($data['period']), null, ['count' => $payslips->count()]);

        return response()->json(['message' => "{$payslips->count()} payslip masuk antrean pengiriman email."]);
    }

    protected function isOwner(Request $request, Payslip $payslip): bool
    {
        return $payslip->register->employee_id === $request->user()->employee?->id;
    }

    /**
     * Karyawan hanya boleh melihat payslip miliknya sendiri.
     */
    protected function authorizePayslip(Request $request, Payslip $payslip, string $adminPermission = 'payslip.view'): void
    {
        $user = $request->user();
        $allowed = $user->hasPermission($adminPermission)
            || ($user->hasPermission('payslip.view_own') && $this->isOwner($request, $payslip));

        if (! $allowed) {
            AuditLog::record('access.denied', $payslip);
            abort(403, 'Anda hanya dapat melihat payslip milik sendiri.');
        }
    }
}
