<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Register Gaji {{ $period->name }}</title>
    @include('partials.pdf-style')
</head>
<body>
    <table class="head">
        <tr>
            <td><div class="brand">{{ config('payroll.company_name') }}</div><div class="muted">Register Gaji (Payroll Register)</div></td>
            <td class="right"><div class="title">Periode {{ $period->name }}</div><div class="muted">Status: {{ \App\Models\PayrollPeriod::STATUSES[$period->status] }} · Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div></td>
        </tr>
    </table>
    <table class="grid">
        <thead><tr><th>No</th><th>NIK</th><th>Nama</th><th>Departemen</th><th class="right">Gaji Pokok</th><th class="right">Allowance</th><th class="right">Deduction</th><th class="right">PPh 21</th><th class="right">Take Home Pay</th><th>Bank</th></tr></thead>
        <tbody>
        @foreach ($registers as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td><td>{{ $r->snapshot['employee_no'] ?? '' }}</td><td>{{ $r->snapshot['full_name'] ?? '' }}</td><td>{{ $r->snapshot['department'] ?? '' }}</td>
                <td class="right">{{ number_format($r->basic_salary, 0, ',', '.') }}</td>
                <td class="right">{{ number_format($r->total_allowance, 0, ',', '.') }}</td>
                <td class="right">{{ number_format($r->total_deduction, 0, ',', '.') }}</td>
                <td class="right">{{ number_format($r->tax_amount, 0, ',', '.') }}</td>
                <td class="right"><b>{{ number_format($r->net_salary, 0, ',', '.') }}</b></td>
                <td>{{ $r->snapshot['bank_name'] ?? '' }} {{ $r->snapshot['account_no'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr>
            <th colspan="4">TOTAL ({{ $registers->count() }} karyawan)</th>
            <th class="right">{{ number_format($registers->sum('basic_salary'), 0, ',', '.') }}</th>
            <th class="right">{{ number_format($registers->sum('total_allowance'), 0, ',', '.') }}</th>
            <th class="right">{{ number_format($registers->sum('total_deduction'), 0, ',', '.') }}</th>
            <th class="right">{{ number_format($registers->sum('tax_amount'), 0, ',', '.') }}</th>
            <th class="right">{{ number_format($registers->sum('net_salary'), 0, ',', '.') }}</th><th></th>
        </tr></tfoot>
    </table>
</body>
</html>
