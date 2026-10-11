<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur register gaji: Buat Periode -> Proses -> Review -> Approval -> Final/Lock -> Generate Payslip.
 */
class PayrollService
{
    public function process(PayrollPeriod $period): int
    {
        $this->guardNotFinal($period);

        $count = (new PayrollCalculator($period))->run();

        $period->update([
            'status' => 'processed',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
        ]);
        AuditLog::record('payroll.process', $period, null, ['registers' => $count]);

        return $count;
    }

    public function approve(PayrollPeriod $period): void
    {
        if ($period->status !== 'processed') {
            throw ValidationException::withMessages(['status' => 'Periode harus berstatus Diproses sebelum di-approve.']);
        }

        $period->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        AuditLog::record('payroll.approve', $period);
    }

    /**
     * Kunci periode: register menjadi snapshot final, cicilan pinjaman dibukukan, payslip dibuat.
     */
    public function finalize(PayrollPeriod $period): void
    {
        if ($period->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Periode harus disetujui (approved) sebelum difinalisasi.']);
        }

        DB::transaction(function () use ($period) {
            $period->registers()->update(['status' => 'final']);

            $installments = LoanInstallment::where('payroll_period_id', $period->id)->where('status', 'scheduled')->get();
            foreach ($installments as $installment) {
                $loan = Loan::find($installment->loan_id);
                $remaining = max(0, (float) $loan->remaining_amount - (float) $installment->amount);
                $loan->update(['remaining_amount' => $remaining, 'status' => $remaining <= 0 ? 'paid' : 'active']);
                $installment->update(['status' => 'paid']);
            }

            foreach ($period->registers()->with('employee')->get() as $register) {
                Payslip::firstOrCreate(['payroll_register_id' => $register->id], [
                    'payslip_no' => sprintf('PAY/%d/%02d/%s', $period->year, $period->month, $register->employee->employee_no),
                    'generated_at' => now(),
                ]);
            }

            $period->update(['status' => 'final', 'finalized_at' => now()]);
        });

        AuditLog::record('payroll.finalize', $period);
    }

    /**
     * Buka kembali periode final (khusus Super Admin, tercatat di audit log).
     */
    public function reopen(PayrollPeriod $period, string $reason): void
    {
        if ($period->status !== 'final') {
            throw ValidationException::withMessages(['status' => 'Hanya periode final yang dapat dibuka kembali.']);
        }

        DB::transaction(function () use ($period) {
            $installments = LoanInstallment::where('payroll_period_id', $period->id)->where('status', 'paid')->get();
            foreach ($installments as $installment) {
                $loan = Loan::find($installment->loan_id);
                $loan->update(['remaining_amount' => (float) $loan->remaining_amount + (float) $installment->amount, 'status' => 'active']);
                $installment->update(['status' => 'scheduled']);
            }

            $period->registers()->update(['status' => 'review']);
            $period->update(['status' => 'processed', 'approved_by' => null, 'approved_at' => null, 'finalized_at' => null]);
        });

        AuditLog::record('payroll.reopen', $period, ['status' => 'final'], ['status' => 'processed', 'reason' => $reason]);
    }

    protected function guardNotFinal(PayrollPeriod $period): void
    {
        if ($period->isLocked()) {
            throw ValidationException::withMessages(['status' => 'Periode sudah final dan terkunci.']);
        }
    }
}
