@extends('layouts.app')

@section('title', 'Register Gaji')
@section('breadcrumb')
    <li class="breadcrumb-item">Kompensasi & Benefit</li>
    <li class="breadcrumb-item active">Register Gaji</li>
@endsection

@php
    $user = auth()->user();
    $status = $period?->status;
    $order = ['draft' => 1, 'processed' => 2, 'approved' => 3, 'final' => 5];
    $level = $order[$status] ?? 0;
    $s = $summary;
    $gross = max(($s->basic ?? 0) + ($s->allowance ?? 0), 1);
@endphp

@section('content')
@if (! $period)
    <div class="card"><div class="empty-state"><span class="material-symbols-outlined">receipt_long</span>Belum ada periode penggajian.
        @can('payroll.process')<div class="mt-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#periodModal">Buat Periode</button></div>@endcan
    </div></div>
@else
<div class="card mb-3">
    <div class="card-body py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 fs-sm">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="material-symbols-outlined text-primary-hc">verified_user</span>
            Periode: <b class="text-primary-hc">{{ $period->name }}</b> · Cut-off: {{ $period->start_date->translatedFormat('d M') }} – {{ $period->end_date->translatedFormat('d M Y') }} · Tgl bayar: {{ $period->pay_date?->translatedFormat('d M Y') }}
            <span class="badge-soft success">{{ (int) ($s->headcount ?? 0) }} Karyawan Terdaftar</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" id="periodSelect" style="min-width:200px">
                @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($p->id === $period->id)>{{ $p->name }} — {{ \App\Models\PayrollPeriod::STATUSES[$p->status] }}</option>@endforeach
            </select>
            @can('payroll.process')<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#periodModal"><span class="material-symbols-outlined ms-sm">add</span>Periode</button>@endcan
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-4 d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h1 class="page-title d-flex align-items-center gap-2 mt-0"><span class="material-symbols-outlined ms-lg text-primary-hc">receipt_long</span>Register Gaji & Review Payroll</h1>
            <div class="page-desc">Periode Penggajian: <b class="text-body">{{ $period->name }}</b> — Status: @include('partials.status-badge', ['status' => $status])</div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            @if ($status !== 'final')
                @can('component.create')<a href="{{ route('components.index', ['tab' => 'adhoc']) }}" class="btn btn-outline-secondary"><span class="material-symbols-outlined">add_circle</span>Input Adhoc / Bonus</a>@endcan
            @endif
            @can('payroll.export')
                <a href="{{ route('payroll.export', [$period, 'xlsx']) }}" class="btn btn-outline-secondary"><span class="material-symbols-outlined">table_view</span>Ekspor Register XLSX</a>
                <a href="{{ route('payroll.export', [$period, 'pdf']) }}" class="btn btn-outline-secondary"><span class="material-symbols-outlined">print</span>Cetak Rekap PDF</a>
            @endcan
            @if (in_array($status, ['draft', 'processed', 'approved']))
                @can('payroll.process')<button class="btn {{ $status === 'draft' ? 'btn-primary' : 'btn-outline-secondary' }} btn-flow" data-url="{{ route('payroll.process', $period) }}" data-confirm="{{ $status === 'draft' ? 'Hitung register gaji untuk seluruh karyawan aktif?' : 'Proses ulang akan menghitung ulang seluruh register dan mereset approval. Lanjutkan?' }}"><span class="material-symbols-outlined">calculate</span>{{ $status === 'draft' ? 'Proses Gaji' : 'Proses Ulang' }}</button>@endcan
            @endif
            @if ($status === 'processed')
                @can('payroll.approve')<button class="btn btn-primary btn-flow" data-url="{{ route('payroll.approve', $period) }}" data-confirm="Setujui register gaji {{ $period->name }}?"><span class="material-symbols-outlined">task_alt</span>Approve Register</button>@endcan
            @endif
            @if ($status === 'approved')
                @can('payroll.finalize')<button class="btn btn-primary btn-flow" data-url="{{ route('payroll.finalize', $period) }}" data-confirm="Kunci periode {{ $period->name }}? Data akan menjadi snapshot final dan payslip digenerate."><span class="material-symbols-outlined">lock</span>Finalisasi & Kunci Periode</button>@endcan
            @endif
            @if ($status === 'final')
                @can('payslip.view')<a href="{{ route('payslips.index', ['period' => $period->id]) }}" class="btn btn-primary"><span class="material-symbols-outlined">payments</span>Lihat Payslip</a>@endcan
                @can('payroll.reopen')<button class="btn btn-outline-danger btn-flow" data-url="{{ route('payroll.reopen', $period) }}" data-confirm="Buka kembali periode final? Tindakan ini tercatat di audit log." data-input="1"><span class="material-symbols-outlined">lock_open</span>Reopen</button>@endcan
            @endif
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <div class="page-eyebrow mb-2">Alur Siklus Penggajian (Payroll Lifecycle Pipeline)</div>
        <div class="pipeline">
            @foreach ([[1, 'Buat Periode', 'Dibuat ' . $period->created_at->translatedFormat('d M')], [2, 'Hitung Gaji Otomatis', $period->processed_at ? 'Selesai ' . $period->processed_at->translatedFormat('d M H:i') : 'Menunggu proses'], [3, 'Review & Approval', $period->approved_at ? 'Disetujui ' . $period->approved_at->translatedFormat('d M') : 'Menunggu persetujuan'], [4, 'Final & Lock', $period->finalized_at ? 'Dikunci ' . $period->finalized_at->translatedFormat('d M') : 'Menunggu finalisasi'], [5, 'Generate Payslip', $status === 'final' ? 'Payslip tersedia' : 'Setelah final']] as [$n, $label, $sub])
                @php $state = $n <= $level ? 'done' : ($n === $level + 1 ? 'current' : ''); @endphp
                <div class="step {{ $state }}">
                    <span class="num">@if ($state === 'done')<span class="material-symbols-outlined ms-sm">check</span>@else{{ $n }}@endif</span>
                    <div class="overflow-hidden"><div class="t">{{ $state === 'current' ? 'Tahap Aktif' : 'Langkah ' . $n }}</div><div class="n text-truncate">{{ $label }}</div><div class="s text-truncate">{{ $sub }}</div></div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl"><div class="card stat-card"><div class="stat-label">Total Gaji Pokok</div><div class="stat-value" style="font-size:1.35rem">@rupiah($s->basic ?? 0)</div><div class="stat-foot">Basis {{ (int) ($s->headcount ?? 0) }} pegawai</div></div></div>
    <div class="col-sm-6 col-xl"><div class="card stat-card"><div class="stat-label">Total Tunjangan (+)</div><div class="stat-value text-primary-hc" style="font-size:1.35rem">@rupiah($s->allowance ?? 0)</div><div class="stat-foot">Jabatan, transport, makan, lembur</div></div></div>
    <div class="col-sm-6 col-xl"><div class="card stat-card"><div class="stat-label">Total Potongan (−)</div><div class="stat-value text-danger" style="font-size:1.35rem">@rupiah($s->deduction ?? 0)</div><div class="stat-foot">BPJS, kasbon, absensi</div></div></div>
    <div class="col-sm-6 col-xl"><div class="card stat-card"><div class="stat-label">Estimasi PPh 21</div><div class="stat-value" style="font-size:1.35rem">@rupiah($s->tax ?? 0)</div><div class="stat-foot">Metode TER PP 58/2023</div></div></div>
    <div class="col-sm-6 col-xl"><div class="card stat-card dark"><div class="stat-label">Net Take Home Pay</div><div class="stat-value" style="font-size:1.35rem">@rupiah($s->net ?? 0)</div><div class="stat-foot">Total siap transfer bank</div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body p-4">
            <h5 class="card-title-hc mb-3">Matriks Approval Payroll</h5>
            @foreach ([['Diproses oleh', $period->processor?->name, $period->processed_at], ['Disetujui oleh', $period->approver?->name, $period->approved_at], ['Finalisasi & Lock', $period->finalized_at ? 'Sistem' : null, $period->finalized_at]] as $i => [$label, $who, $at])
                <div class="d-flex gap-2 mb-3 fs-sm">
                    <span class="material-symbols-outlined {{ $at ? 'text-primary-hc' : 'text-muted-hc' }}">{{ $at ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <div class="flex-grow-1"><b>{{ $i + 1 }}. {{ $label }}</b><div class="cell-sub">{{ $who ?? '—' }}</div></div>
                    <span class="fs-xs {{ $at ? 'text-primary-hc' : 'text-muted-hc' }}">{{ $at?->translatedFormat('d M, H:i') ?? 'Menunggu' }}</span>
                </div>
            @endforeach
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between"><h5 class="card-title-hc">Komposisi Beban Gaji</h5><span class="fs-xs text-muted-hc">Rasio Net/Gross: {{ round(($s->net ?? 0) / $gross * 100, 1) }}%</span></div>
            @php $parts = [['Pokok', $s->basic ?? 0, '#059669'], ['Tunj.', $s->allowance ?? 0, '#34d399'], ['Pot.', $s->deduction ?? 0, '#dc2626'], ['Pajak', $s->tax ?? 0, '#64748b']]; $sumParts = max(collect($parts)->sum(1), 1); @endphp
            <div class="stack-bar my-3">@foreach ($parts as [$l, $v, $c])<div style="width: {{ $v / $sumParts * 100 }}%; background: {{ $c }}"></div>@endforeach</div>
            <div class="d-flex flex-wrap gap-3 fs-xs">@foreach ($parts as [$l, $v, $c])<span><span class="legend-dot rounded-circle" style="background: {{ $c }}"></span>{{ $l }} {{ round($v / $sumParts * 100) }}%</span>@endforeach</div>
            <p class="fs-xs text-muted-hc mt-3 mb-0">Perhitungan memakai gaji pokok aktif, komponen tetap & sekali bayar, lembur, potongan absensi, cicilan, BPJS, dan PPh 21 TER.</p>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body p-4">
            <h5 class="card-title-hc mb-3">Rekonsiliasi Absensi & Lembur</h5>
            <div class="row g-2">
                <div class="col-6"><div class="mini-stat text-start"><div class="lbl">Total Jam Lembur</div><div class="val">{{ (float) ($s->overtime_hours ?? 0) }} Jam</div><div class="cell-sub">@rupiah($itemTotals['ALW-OVT'] ?? 0)</div></div></div>
                <div class="col-6"><div class="mini-stat text-start"><div class="lbl">Potongan Terlambat</div><div class="val text-danger">@rupiah($itemTotals['DED-LAT'] ?? 0)</div><div class="cell-sub">{{ (int) ($s->late_count ?? 0) }} kejadian</div></div></div>
                <div class="col-6"><div class="mini-stat text-start"><div class="lbl">Potongan Alpha</div><div class="val text-danger">@rupiah($itemTotals['DED-ALP'] ?? 0)</div></div></div>
                <div class="col-6"><div class="mini-stat text-start"><div class="lbl">Perlu Review</div><div class="val">{{ (int) ($s->review_count ?? 0) }}</div><div class="cell-sub">ada komponen adhoc</div></div></div>
            </div>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <form class="p-3 border-bottom row g-2 align-items-center" id="registerFilter">
        <div class="col-lg-4"><div class="input-icon"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari NIK, nama karyawan..."></div></div>
        <div class="col-sm-6 col-lg-3"><select name="department_id" class="form-select"><option value="">Semua Departemen</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-3"><select name="status" class="form-select"><option value="">Semua Status Verifikasi</option><option value="verified">Verified</option><option value="review">Perlu Review</option><option value="final">Final</option></select></div>
    </form>
    <div class="table-responsive">
        <table class="table table-hc table-hover">
            <thead><tr><th>NIK</th><th>Nama Karyawan</th><th>Departemen</th><th class="text-end">Gaji Pokok</th><th class="text-end">Total Allowance (+)</th><th class="text-end">Total Potongan (−)</th><th class="text-end">PPh 21</th><th class="text-end">Net Take Home Pay</th><th>Status</th><th></th></tr></thead>
            <tbody id="registerRows"></tbody>
        </table>
    </div>
    <div class="table-footer" id="registerFooter"></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title-hc d-flex align-items-center gap-2"><span class="material-symbols-outlined text-primary-hc">history</span>Catatan Riwayat Audit Register (Audit Trail)</h5>
        @can('audit.view')<a href="{{ route('settings.audit-logs', ['entity' => 'PayrollPeriod']) }}" class="fs-sm fw-600 text-decoration-none">Lihat Log Lengkap</a>@endcan
    </div>
    <div class="card-body row g-2">
        @forelse ($audits as $a)
            <div class="col-md-4"><div class="list-row align-items-start h-100"><span class="material-symbols-outlined text-primary-hc">verified</span>
                <div><div class="fw-600">{{ $a->action }}</div><div class="cell-sub">Oleh {{ $a->user?->name ?? 'Sistem' }}{{ isset($a->new_values['reason']) ? ' — ' . $a->new_values['reason'] : '' }}</div><div class="fs-xs text-muted-hc">{{ $a->created_at->translatedFormat('d M Y · H:i:s') }}</div></div></div></div>
        @empty
            <div class="text-muted-hc fs-sm">Belum ada aktivitas.</div>
        @endforelse
    </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">receipt</span></div>
                <div class="flex-grow-1"><h5 class="modal-title reg-name">Detail Register</h5><p class="modal-sub reg-sub"></p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body reg-body"></div>
        </div>
    </div>
</div>
@endif

@can('payroll.process')
<div class="modal fade" id="periodModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="periodForm" action="{{ route('payroll.periods.store') }}" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">calendar_month</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Buat Periode Penggajian</h5><p class="modal-sub">Langkah 1 dari siklus register gaji.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-6"><label class="form-label">Bulan <span class="req">*</span></label><select name="month" class="form-select">@foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m === now()->month)>{{ \Carbon\Carbon::create(null, $m)->translatedFormat('F') }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Tahun <span class="req">*</span></label><input type="number" name="year" class="form-control" value="{{ now()->year }}"></div>
                <div class="col-12"><label class="form-label">Tanggal Pembayaran</label><input type="date" name="pay_date" class="form-control"></div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Buat Periode</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection

@push('scripts')
<script>
$(function () {
    $('#periodSelect').on('change', function () { location.href = @json(route('payroll.index')) + '?period=' + this.value; });

    $('#periodForm').on('submit', function (e) {
        e.preventDefault();
        HCMS.submitForm($(this), { onSuccess: (res) => { location.href = res.redirect; } });
    });

    $(document).on('click', '.btn-flow', function () {
        const $b = $(this);
        HCMS.action($b.data('url'), 'POST', {
            confirm: $b.data('confirm'), input: !!$b.data('input'), danger: $b.hasClass('btn-outline-danger'),
            onSuccess: () => setTimeout(() => location.reload(), 600)
        });
    });

    @if ($period)
    const table = new HCMS.Table({
        url: @json(route('payroll.registers', $period)), tbody: '#registerRows', footer: '#registerFooter', filters: '#registerFilter', colspan: 10,
        render: (r) => `<tr>
            <td class="code-link">${HCMS.esc(r.employee_no)}</td>
            <td><div class="d-flex align-items-center gap-2">${HCMS.avatar(r.full_name)}<div><div class="cell-title">${HCMS.esc(r.full_name)}</div><div class="cell-sub">${HCMS.esc(r.position || '')}</div></div></div></td>
            <td><span class="badge-soft slate">${HCMS.esc(r.department || '-')}</span></td>
            <td class="text-num">${HCMS.rupiah(r.basic_salary)}</td>
            <td class="text-num text-primary-hc">+${HCMS.rupiah(r.total_allowance)}</td>
            <td class="text-num text-danger">−${HCMS.rupiah(r.total_deduction)}</td>
            <td class="text-num">${HCMS.rupiah(r.tax_amount)}</td>
            <td class="text-num col-net">${HCMS.rupiah(r.net_salary)}</td>
            <td>${HCMS.badge(r.status)}</td>
            <td><button class="btn-icon btn-detail" data-id="${r.id}" title="Detail"><span class="material-symbols-outlined">receipt</span></button></td></tr>`
    });
    table.load();

    $(document).on('click', '.btn-detail', function () {
        $.getJSON(`${@json(url('payroll/registers'))}/${$(this).data('id')}`).done((res) => {
            const r = res.register, sn = r.snapshot || {};
            const lines = (items, sign) => items.map((i) => `<div class="slip-line"><span>${HCMS.esc(i.description)}</span><span class="mono">${sign}${HCMS.rupiah(i.amount)}</span></div>`).join('') || '<div class="slip-line text-muted-hc">—</div>';
            $('#registerModal .reg-name').text(sn.full_name);
            $('#registerModal .reg-sub').text(`${sn.employee_no} · ${sn.department || ''} · ${res.period}`);
            $('#registerModal .reg-body').html(`
                <div class="row g-3 mb-3 fs-sm">
                    <div class="col-3"><div class="mini-stat"><div class="lbl">Hadir</div><div class="val">${r.present_days}</div></div></div>
                    <div class="col-3"><div class="mini-stat"><div class="lbl">Alpha</div><div class="val text-danger">${r.absent_days}</div></div></div>
                    <div class="col-3"><div class="mini-stat"><div class="lbl">Terlambat</div><div class="val">${r.late_count}x</div></div></div>
                    <div class="col-3"><div class="mini-stat"><div class="lbl">Lembur</div><div class="val">${Number(r.overtime_hours)} j</div></div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><div class="slip-box"><div class="slip-box-head text-primary-hc">A. Pendapatan</div>
                        <div class="slip-line"><span>Gaji Pokok</span><span class="mono">${HCMS.rupiah(r.basic_salary)}</span></div>${lines(res.allowances, '+')}
                        <div class="slip-total"><span>Total Bruto</span><span>${HCMS.rupiah(Number(r.basic_salary) + Number(r.total_allowance))}</span></div></div></div>
                    <div class="col-md-6"><div class="slip-box"><div class="slip-box-head text-danger">B. Potongan</div>${lines(res.deductions, '−')}
                        <div class="slip-total"><span>Total Potongan</span><span class="text-danger">${HCMS.rupiah(Number(r.total_deduction) + Number(r.tax_amount))}</span></div></div></div>
                </div>
                <div class="slip-net mt-3 d-flex justify-content-between align-items-center"><span>Take Home Pay</span><span class="amount">${HCMS.rupiah(r.net_salary)}</span></div>`);
            bootstrap.Modal.getOrCreateInstance($('#registerModal')[0]).show();
        });
    });
    @endif
});
</script>
@endpush
