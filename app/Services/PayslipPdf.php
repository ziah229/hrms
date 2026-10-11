<?php

namespace App\Services;

use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;

class PayslipPdf
{
    /**
     * Render payslip ke PDF. Bila $protect = true, PDF diberi password tanggal lahir (ddmmyyyy),
     * atau 6 digit terakhir NIK KTP bila tanggal lahir kosong.
     */
    public function render(Payslip $payslip, bool $protect = true): string
    {
        $payslip->loadMissing(['register.items', 'register.period', 'register.employee']);

        $pdf = Pdf::loadView('payslips.pdf', ['payslip' => $payslip, 'company' => \App\Models\Company::first()])->setPaper('a4');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        if ($protect && ($password = $this->password($payslip))) {
            $dompdf->getCanvas()->get_cpdf()->setEncryption($password, $password.'-owner', ['print']);
        }

        return $dompdf->output();
    }

    public function password(Payslip $payslip): ?string
    {
        $employee = $payslip->register->employee;

        return $employee->birth_date?->format('dmY') ?? ($employee->nik_ktp ? substr($employee->nik_ktp, -6) : null);
    }

    public function filename(Payslip $payslip): string
    {
        return 'payslip-'.str_replace('/', '-', $payslip->payslip_no).'.pdf';
    }
}
