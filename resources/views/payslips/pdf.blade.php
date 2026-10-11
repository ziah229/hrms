@php
    $r = $payslip->register;
    $sn = $r->snapshot ?? [];
    $allow = $r->items->where('type', 'allowance')->values();
    $deduct = $r->items->where('type', 'deduction')->values();
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $rows = max($allow->count() + 1, $deduct->count());
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $payslip->payslip_no }}</title>
    @include('partials.pdf-style')
    <style>
        body { font-size: 10px; }
        .slip-title { background: #ecfdf5; border-left: 4px solid #059669; padding: 8px 10px; margin: 10px 0; }
        .info td { padding: 3px 4px; vertical-align: top; }
        .info .lbl { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .items td { padding: 4px 6px; }
        .net { background: #047857; color: #fff; padding: 10px 12px; margin-top: 10px; }
        .net .amount { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td><div class="brand">{{ $company?->name }}</div><div class="muted">{{ $company?->address }}</div><div class="muted">NPWP: {{ $company?->npwp }}</div></td>
            <td class="right" style="color:#b91c1c;font-weight:bold">DOKUMEN RAHASIA</td>
        </tr>
    </table>

    <table class="slip-title" width="100%">
        <tr>
            <td><b style="color:#065f46;font-size:12px">SLIP GAJI KARYAWAN / PAYSLIP</b><br>Periode: {{ $r->period->start_date->translatedFormat('d F Y') }} – {{ $r->period->end_date->translatedFormat('d F Y') }}</td>
            <td class="right"><span class="muted">NO. SLIP</span><br><b>{{ $payslip->payslip_no }}</b></td>
        </tr>
    </table>

    <table class="info" width="100%">
        <tr>
            <td width="25%"><div class="lbl">Nama Karyawan</div><b>{{ $sn['full_name'] ?? '' }}</b></td>
            <td width="25%"><div class="lbl">NIK Karyawan</div><b>{{ $sn['employee_no'] ?? '' }}</b></td>
            <td width="25%"><div class="lbl">Departemen</div>{{ $sn['department'] ?? '' }}</td>
            <td width="25%"><div class="lbl">Jabatan</div>{{ $sn['position'] ?? '' }}</td>
        </tr>
        <tr>
            <td><div class="lbl">Status PTKP</div>{{ $sn['ptkp_status'] ?? '-' }}</td>
            <td><div class="lbl">Rekening</div>{{ $sn['bank_name'] ?? '' }} - {{ $sn['account_no'] ?? '' }}</td>
            <td colspan="2"><div class="lbl">Rekap Kehadiran</div>Hadir {{ $r->present_days }} hari · Alpha {{ $r->absent_days }} · Terlambat {{ $r->late_count }}x · Lembur {{ (float) $r->overtime_hours }} jam</td>
        </tr>
    </table>

    <table class="grid items" style="margin-top:10px">
        <thead><tr><th width="35%">A. PENDAPATAN</th><th width="15%" class="right">IDR</th><th width="35%" style="background:#b91c1c">B. POTONGAN</th><th width="15%" class="right" style="background:#b91c1c">IDR</th></tr></thead>
        <tbody>
        @for ($i = 0; $i < $rows; $i++)
            @php $a = $i === 0 ? ['description' => 'Gaji Pokok', 'amount' => $r->basic_salary] : $allow->get($i - 1); $d = $deduct->get($i); @endphp
            <tr>
                <td>{{ $a['description'] ?? '' }}</td><td class="right">{{ $a ? $rp($a['amount']) : '' }}</td>
                <td>{{ $d['description'] ?? '' }}</td><td class="right">{{ $d ? $rp($d['amount']) : '' }}</td>
            </tr>
        @endfor
        </tbody>
        <tfoot><tr>
            <th>Total Pendapatan Kotor</th><th class="right">{{ $rp($r->basic_salary + $r->total_allowance) }}</th>
            <th style="background:#fef2f2;color:#b91c1c;border-top-color:#b91c1c">Total Potongan</th><th class="right" style="background:#fef2f2;color:#b91c1c;border-top-color:#b91c1c">{{ $rp($r->total_deduction + $r->tax_amount) }}</th>
        </tr></tfoot>
    </table>

    <div class="net">
        <div style="font-size:8px;letter-spacing:1px">PENERIMAAN BERSIH (TAKE HOME PAY)</div>
        <div class="amount">{{ $rp($r->net_salary) }}</div>
        <div style="font-style:italic;font-size:9px">Terbilang: "{{ \App\Support\Terbilang::make($r->net_salary) }} Rupiah"</div>
    </div>

    <table width="100%" style="margin-top:16px">
        <tr>
            <td class="muted" width="60%">Dokumen ini diterbitkan secara elektronik oleh sistem NexaHCMS dan sah tanpa tanda tangan basah.</td>
            <td class="right">Jakarta, {{ $payslip->generated_at?->translatedFormat('d F Y') }}<br><br><br><b>Finance & Payroll Department</b></td>
        </tr>
    </table>
</body>
</html>
