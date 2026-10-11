@extends('layouts.app')

@section('title', 'Report')
@section('breadcrumb')
    <li class="breadcrumb-item">Laporan</li>
    <li class="breadcrumb-item active">Report</li>
@endsection

@php $icons = ['Karyawan' => 'groups', 'Absensi' => 'event_available', 'Gaji' => 'payments', 'Allowance/Deduction' => 'sell', 'Pajak & BPJS' => 'account_balance', 'Transfer Bank' => 'sync_alt']; @endphp

@section('content')
<div class="mb-4">
    <div class="page-eyebrow">Laporan & Sistem · <span class="accent">Report Center</span></div>
    <h1 class="page-title">Pusat Laporan</h1>
    <p class="page-desc">1) Pilih jenis report → 2) isi filter → 3) preview → 4) export XLSX atau PDF.</p>
</div>

<div class="row g-3">
    <div class="col-xl-3">
        <div class="card">
            <div class="card-header"><h5 class="card-title-hc">1. Jenis Report</h5></div>
            <div class="list-group list-group-flush" id="reportList">
                @forelse ($reports as $category => $items)
                    <div class="list-group-item bg-light page-eyebrow py-2"><span class="material-symbols-outlined ms-sm me-1">{{ $icons[$category] ?? 'description' }}</span>{{ $category }}</div>
                    @foreach ($items as $r)
                        <a href="#" class="list-group-item list-group-item-action fs-sm report-item" data-code="{{ $r->code }}" data-params="{{ json_encode($r->parameters) }}">{{ $r->name }}</a>
                    @endforeach
                @empty
                    <div class="list-group-item text-muted-hc fs-sm">Tidak ada report yang dapat Anda akses.</div>
                @endforelse
            </div>
        </div>
        @if ($recent->isNotEmpty())
            <div class="card mt-3">
                <div class="card-header"><h5 class="card-title-hc">Export Terakhir</h5></div>
                <ul class="list-group list-group-flush fs-xs">
                    @foreach ($recent as $e)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $e->definition->name }}</span><span class="badge-soft slate">{{ strtoupper($e->format) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <div class="col-xl-9">
        <div class="card mb-3">
            <form class="card-body row g-3 align-items-end" id="reportFilter">
                <div class="col-12"><h5 class="card-title-hc mb-0">2. Filter <span class="fs-sm text-muted-hc fw-normal report-name">— pilih report terlebih dahulu</span></h5></div>
                <div class="col-md-3 param param-month d-none"><label class="form-label">Bulan</label><input type="month" name="month" class="form-control" value="{{ now()->format('Y-m') }}"></div>
                <div class="col-md-3 param param-period d-none"><label class="form-label">Periode Gaji</label>
                    <select name="period" class="form-select">@foreach ($periods as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ \App\Models\PayrollPeriod::STATUSES[$p->status] }})</option>@endforeach</select></div>
                <div class="col-md-3 param param-department d-none"><label class="form-label">Departemen</label>
                    <select name="department_id" class="form-select"><option value="">Semua</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                <div class="col-md-3 param param-status d-none"><label class="form-label">Status Kerja</label>
                    <select name="status" class="form-select"><option value="">Semua</option>@foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-3 ms-auto d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" disabled id="btnPreview"><span class="material-symbols-outlined">visibility</span>3. Preview</button>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><h5 class="card-title-hc preview-title">Preview</h5><p class="card-sub preview-sub"></p></div>
                @can('report.export')
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary disabled btn-export" data-format="xlsx"><span class="material-symbols-outlined">table_view</span>4. Export XLSX</a>
                        <a class="btn btn-outline-secondary disabled btn-export" data-format="pdf"><span class="material-symbols-outlined">picture_as_pdf</span>Export PDF</a>
                    </div>
                @endcan
            </div>
            <div class="table-responsive" style="max-height:560px">
                <table class="table table-hc table-hover"><thead id="previewHead"></thead><tbody id="previewBody">
                    <tr><td><div class="empty-state"><span class="material-symbols-outlined">bar_chart</span>Pilih report dan klik Preview.</div></td></tr>
                </tbody></table>
            </div>
            <div class="table-footer preview-foot d-none"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>.report-item.active { background: var(--hc-primary-light); color: var(--hc-primary-hover); font-weight: 600; border-left: 3px solid var(--hc-primary); }</style>
@endpush

@push('scripts')
<script>
$(function () {
    const base = @json(url('reports'));
    let code = null;

    $('.report-item').on('click', function (e) {
        e.preventDefault();
        $('.report-item').removeClass('active'); $(this).addClass('active');
        code = $(this).data('code');
        const params = $(this).data('params') || [];
        $('.param').addClass('d-none');
        params.forEach((p) => $(`.param-${p}`).removeClass('d-none'));
        $('.report-name').text('— ' + $(this).text());
        $('#btnPreview').prop('disabled', false);
        $('#reportFilter').trigger('submit');
    });

    function query() {
        return $('#reportFilter .param:not(.d-none)').find('input, select').serialize();
    }

    $('#reportFilter').on('submit', function (e) {
        e.preventDefault();
        if (!code) return;
        $('#previewBody').html('<tr class="table-loading"><td><span class="spinner-border spinner-border-sm text-success me-2"></span>Menyusun report...</td></tr>');
        $.getJSON(`${base}/${code}/preview?${query()}`).done((res) => {
            $('.preview-title').text(res.title);
            $('.preview-sub').text(res.subtitle + ' · ' + res.total + ' baris');
            $('#previewHead').html('<tr>' + res.columns.map((c, i) => `<th class="${res.money.includes(i) ? 'text-end' : ''}">${HCMS.esc(c)}</th>`).join('') + '</tr>');
            $('#previewBody').html(res.rows.length
                ? res.rows.map((r) => '<tr>' + r.map((v, i) => res.money.includes(i) ? `<td class="text-num">${HCMS.rupiah(v)}</td>` : `<td>${HCMS.esc(v)}</td>`).join('') + '</tr>').join('')
                : `<tr><td colspan="${res.columns.length}"><div class="empty-state"><span class="material-symbols-outlined">inbox</span>Tidak ada data untuk filter ini.</div></td></tr>`);
            $('.preview-foot').toggleClass('d-none', res.total <= res.rows.length).text(`Preview menampilkan ${res.rows.length} dari ${res.total} baris. Export untuk data lengkap.`);
            $('.btn-export').removeClass('disabled').each(function () {
                this.href = `${base}/${code}/export?format=${$(this).data('format')}&${query()}`;
            });
        });
    });
});
</script>
@endpush
