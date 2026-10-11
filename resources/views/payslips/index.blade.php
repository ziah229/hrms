@extends('layouts.app')

@section('title', 'Payslip')
@section('breadcrumb')
    <li class="breadcrumb-item">Payslip</li>
    <li class="breadcrumb-item active">Preview & Cetak Slip Gaji</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body p-4 d-flex flex-wrap justify-content-between gap-3">
        <div class="d-flex gap-3">
            <span class="stat-icon green" style="width:48px;height:48px"><span class="material-symbols-outlined ms-lg">payments</span></span>
            <div>
                <h1 class="page-title mt-0">Daftar & Distribusi Slip Gaji (Employee Payslip)</h1>
                <p class="page-desc">Distribusi slip gaji elektronik terenkripsi, cetak format PDF, dan monitoring pengiriman email massal.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <select class="form-select" id="periodSelect" style="min-width:240px">
                @forelse ($periods as $p)<option value="{{ $p->id }}" @selected($p->id === $period?->id)>{{ $p->name }} (Final & Locked)</option>@empty<option>Belum ada periode final</option>@endforelse
            </select>
            @if ($period)
                @can('payslip.send')<button class="btn btn-primary" id="btnSendAll"><span class="material-symbols-outlined">outgoing_mail</span>Kirim Massal Email</button>@endcan
            @endif
        </div>
    </div>
</div>

@if (! $period)
    <div class="card"><div class="empty-state"><span class="material-symbols-outlined">receipt_long</span>Payslip dibuat otomatis setelah periode register gaji difinalisasi.</div></div>
@else
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Total Karyawan Payroll</span><span class="stat-icon green"><span class="material-symbols-outlined">badge</span></span></div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-foot">100% payroll terkalkulasi</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Email Terdistribusi</span><span class="stat-icon green"><span class="material-symbols-outlined">mark_email_read</span></span></div><div class="stat-value">{{ $stats['sent'] }} <small>/ {{ $stats['total'] }}</small></div><div class="stat-foot text-primary-hc">{{ $stats['total'] ? round($stats['sent'] / $stats['total'] * 100, 1) : 0 }}% tingkat keberhasilan</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Telah Dibuka (Read)</span><span class="stat-icon slate"><span class="material-symbols-outlined">drafts</span></span></div><div class="stat-value">{{ $stats['viewed'] }}</div><div class="stat-foot">Dibuka via self service</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Belum Terkirim</span><span class="stat-icon red"><span class="material-symbols-outlined">unsubscribe</span></span></div><div class="stat-value text-danger">{{ $stats['total'] - $stats['sent'] }}</div><div class="stat-foot">Memerlukan konfirmasi HR</div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="card-title-hc">Daftar Karyawan {{ $period->name }}</h5><span class="count-pill">{{ $stats['total'] }} Data</span></div>
                <form id="slipFilter">
                    <div class="input-icon mb-2"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari NIK, nama..."></div>
                    <input type="hidden" name="period" value="{{ $period->id }}">
                    <input type="hidden" name="filter" value="">
                    <div class="d-flex flex-wrap gap-1 filter-chips">
                        @foreach (['' => 'Semua', 'sent' => 'Terkirim', 'unsent' => 'Belum Dikirim', 'viewed' => 'Dibuka'] as $k => $v)
                            <button type="button" class="btn btn-sm {{ $k === '' ? 'btn-primary' : 'btn-outline-secondary' }} chip" data-filter="{{ $k }}">{{ $v }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
            <div class="payslip-list" id="slipList"></div>
            <div class="table-footer" id="slipFooter"></div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-2">
            <div class="card-body py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fs-xs text-primary-hc"><span class="material-symbols-outlined ms-sm">verified_user</span> Status Dokumen: Sah & Terenkripsi</span>
                <div class="d-flex flex-wrap gap-2 slip-actions">
                    @can('payslip.send')<button class="btn btn-sm btn-outline-secondary" id="btnSendOne" disabled><span class="material-symbols-outlined ms-sm">send</span>Kirim Ulang Email</button>@endcan
                    @can('payslip.export')
                        <a class="btn btn-sm btn-primary disabled" id="btnPdf" target="_blank"><span class="material-symbols-outlined ms-sm">print</span>Cetak Slip (PDF)</a>
                        <a class="btn btn-sm btn-outline-secondary disabled" id="btnPdfProtected"><span class="material-symbols-outlined ms-sm">lock</span>Unduh PDF Terproteksi</a>
                    @endcan
                </div>
            </div>
        </div>
        <div id="slipPreview"><div class="card"><div class="empty-state"><span class="material-symbols-outlined">description</span>Pilih karyawan untuk melihat preview slip gaji.</div></div></div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
$(function () {
    $('#periodSelect').on('change', function () { location.href = @json(route('payslips.index')) + '?period=' + this.value; });
    @if ($period)
    const base = @json(url('payslips'));
    let selected = null;

    const table = new HCMS.Table({
        url: @json(route('payslips.index')), tbody: '#slipList', footer: '#slipFooter', filters: '#slipFilter', perPage: 8, list: true,
        render: (r) => `<div class="item ${selected === r.id ? 'active' : ''}" data-id="${r.id}">
            ${HCMS.avatar(r.full_name)}
            <div class="flex-grow-1 overflow-hidden">
                <div class="d-flex justify-content-between gap-2"><span class="cell-title text-truncate">${HCMS.esc(r.full_name)} <span class="badge-soft success ms-1">${HCMS.esc(r.employee_no)}</span></span>
                    ${r.emailed_at ? '<span class="badge-soft success">Terkirim</span>' : '<span class="badge-soft danger">Belum Dikirim</span>'}</div>
                <div class="cell-sub text-truncate">${HCMS.esc(r.position)} · ${HCMS.esc(r.department)}</div>
                <div class="d-flex justify-content-between fs-xs"><span>Take Home Pay: <b class="text-primary-hc">${HCMS.rupiah(r.net_salary)}</b></span><span class="text-muted-hc">${r.viewed_at ? 'Dibuka ' + r.viewed_at : (r.emailed_at || '')}</span></div>
            </div></div>`,
        onLoad: (res) => { if (!selected && res.data.length) select(res.data[0].id); }
    });
    table.load();

    function select(id) {
        selected = id;
        $('#slipList .item').removeClass('active').filter(`[data-id="${id}"]`).addClass('active');
        $('#slipPreview').html('<div class="card"><div class="empty-state"><span class="spinner-border text-success"></span></div></div>');
        $.get(`${base}/${id}/preview`).done((html) => $('#slipPreview').html(html));
        $('#btnPdf').attr('href', `${base}/${id}/pdf?inline=1&unprotected=1`).removeClass('disabled');
        $('#btnPdfProtected').attr('href', `${base}/${id}/pdf`).removeClass('disabled');
        $('#btnSendOne').prop('disabled', false);
    }
    $(document).on('click', '#slipList .item', function () { select($(this).data('id')); });

    $('.filter-chips').on('click', '.chip', function () {
        $('.filter-chips .chip').removeClass('btn-primary').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
        $('#slipFilter [name="filter"]').val($(this).data('filter'));
        table.load(1);
    });

    $('#btnSendOne').on('click', () => selected && HCMS.action(`${base}/${selected}/send`, 'POST', { onSuccess: () => table.reload() }));
    $('#btnSendAll').on('click', () => HCMS.action(@json(route('payslips.send-bulk')), 'POST', {
        confirm: 'Kirim payslip {{ $period->name }} ke email seluruh karyawan yang belum menerima?',
        data: { period: {{ $period->id }}, only_unsent: 1 }, onSuccess: () => table.reload()
    }));
    @endif
});
</script>
@endpush
