@php
    $r = $payslip->register;
    $sn = $r->snapshot ?? [];
    $allow = $r->items->where('type', 'allowance');
    $deduct = $r->items->where('type', 'deduction');
    $gross = $r->basic_salary + $r->total_allowance;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp
<div class="slip">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <div class="d-flex gap-2">
            <img src="{{ asset('img/logo-mark.svg') }}" width="40" height="40" alt="">
            <div>
                <div class="fw-bold">{{ $company?->name }}</div>
                <div class="fs-xs text-muted-hc" style="max-width:320px">{{ $company?->address }} · Telp: {{ $company?->phone }}</div>
                <div class="fs-xs text-muted-hc">NPWP: {{ $company?->npwp }}</div>
            </div>
        </div>
        <div class="text-end"><span class="badge-soft danger">DOKUMEN RAHASIA (CONFIDENTIAL)</span></div>
    </div>

    <div class="slip-title d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div><div class="fw-bold text-primary-hc fs-6">SLIP GAJI KARYAWAN / PAYSLIP</div><div class="fs-xs">Periode Penggajian: {{ $r->period->start_date->translatedFormat('d F Y') }} – {{ $r->period->end_date->translatedFormat('d F Y') }}</div></div>
        <div class="text-end"><div class="fs-xs text-muted-hc">NO. SLIP GAJI</div><div class="fw-bold mono">{{ $payslip->payslip_no }}</div><div class="fs-xs text-muted-hc">Diterbitkan: {{ $payslip->generated_at?->translatedFormat('d M Y') }}</div></div>
    </div>

    <div class="row g-3 mb-3 fs-sm">
        @foreach ([['Nama Karyawan', $sn['full_name'] ?? ''], ['Nomor Induk (NIK)', $sn['employee_no'] ?? ''], ['Departemen', $sn['department'] ?? ''], ['Jabatan', $sn['position'] ?? ''],
                   ['Status Kepegawaian', \App\Models\Employee::EMPLOYMENT_STATUSES[$sn['employment_status'] ?? ''] ?? '-'], ['Rekening Bank', ($sn['bank_name'] ?? '') . ' - ' . ($sn['account_no'] ?? '')],
                   ['Status PTKP / NPWP', ($sn['ptkp_status'] ?? '-') . ' / ' . ($sn['npwp'] ? 'Ada' : 'Tidak ada')], ['Rekap Kehadiran', "Hadir: {$r->present_days} Hari | Alpha: {$r->absent_days} | Terlambat: {$r->late_count}x"]] as [$l, $v])
            <div class="col-6 col-md-3"><div class="fs-xs text-muted-hc text-uppercase fw-600">{{ $l }}</div><div class="fw-600">{{ $v }}</div></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="slip-box h-100 d-flex flex-column">
                <div class="slip-box-head text-primary-hc d-flex justify-content-between"><span>A. Pendapatan (Earnings)</span><span class="fs-xs text-muted-hc">IDR</span></div>
                <div class="slip-line"><span>Gaji Pokok (Basic Salary)</span><span class="mono">{{ $rp($r->basic_salary) }}</span></div>
                @foreach ($allow as $i)<div class="slip-line"><span>{{ $i->description }}</span><span class="mono">{{ $rp($i->amount) }}</span></div>@endforeach
                <div class="slip-total mt-auto"><span>Total Pendapatan Kotor (Gross)</span><span class="text-primary-hc">{{ $rp($gross) }}</span></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="slip-box h-100 d-flex flex-column">
                <div class="slip-box-head text-danger d-flex justify-content-between"><span>B. Potongan (Deductions)</span><span class="fs-xs text-muted-hc">Resmi & Pajak</span></div>
                @forelse ($deduct as $i)<div class="slip-line"><span>{{ $i->description }}</span><span class="mono">{{ $rp($i->amount) }}</span></div>@empty<div class="slip-line text-muted-hc">—</div>@endforelse
                <div class="slip-total mt-auto"><span>Total Potongan</span><span class="text-danger">{{ $rp($r->total_deduction + $r->tax_amount) }}</span></div>
            </div>
        </div>
    </div>

    <div class="slip-net mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="fs-xs text-uppercase fw-600" style="letter-spacing:.06em;color:#a7f3d0">Penerimaan Bersih Karyawan</div>
            <div class="amount">{{ $rp($r->net_salary) }}</div>
            <div class="fs-xs fst-italic" style="color:#d1fae5">Terbilang: "{{ \App\Support\Terbilang::make($r->net_salary) }} Rupiah"</div>
        </div>
        <div class="text-end fs-xs"><div style="color:#a7f3d0">DISALURKAN MELALUI</div><div class="fw-bold">{{ $sn['bank_name'] ?? '-' }} Payroll</div><div>Tgl bayar: {{ $r->period->pay_date?->translatedFormat('d M Y') }}</div></div>
    </div>

    <div class="d-flex flex-wrap justify-content-between gap-3 mt-3 fs-xs text-muted-hc">
        <div style="max-width:420px"><b>Catatan Keamanan:</b> Slip gaji ini diterbitkan otomatis oleh sistem NexaHCMS. PDF dilindungi password berformat tanggal lahir DDMMYYYY.</div>
        <div class="text-end"><div>Jakarta, {{ $payslip->generated_at?->translatedFormat('d F Y') }}</div><div class="fw-600 text-body mt-1">Finance & Payroll Department</div></div>
    </div>
</div>
