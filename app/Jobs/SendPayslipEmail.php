<?php

namespace App\Jobs;

use App\Mail\PayslipMail;
use App\Models\Payslip;
use App\Services\PayslipPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPayslipEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Payslip $payslip) {}

    public function handle(PayslipPdf $pdf): void
    {
        $employee = $this->payslip->register->employee;
        if (! $employee->email) {
            return;
        }

        Mail::to($employee->email)->send(new PayslipMail($this->payslip, $pdf->render($this->payslip), $pdf->filename($this->payslip)));

        $this->payslip->update(['emailed_at' => now()]);
    }
}
