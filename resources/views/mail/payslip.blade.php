<!DOCTYPE html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #0f172a; font-size: 14px;">
    <p>Yth. {{ $payslip->register->employee->full_name }},</p>
    <p>Terlampir slip gaji Anda untuk periode <b>{{ $payslip->register->period->name }}</b> (No. {{ $payslip->payslip_no }}).</p>
    <p>Dokumen PDF dilindungi password: <b>tanggal lahir Anda dengan format DDMMYYYY</b> (contoh: 17081990).</p>
    <p>Dokumen ini bersifat rahasia. Mohon tidak meneruskan kepada pihak lain.</p>
    <p>Salam,<br>Tim Payroll — {{ config('payroll.company_name') }}</p>
</body>
</html>
