@extends('layouts.app')

@section('title', 'Payslip Saya')
@section('breadcrumb')
    <li class="breadcrumb-item">Payslip</li>
    <li class="breadcrumb-item active">Payslip Saya</li>
@endsection

@section('content')
<div class="mb-4">
    <div class="page-eyebrow">Self Service · <span class="accent">Payslip</span></div>
    <h1 class="page-title">Payslip Saya</h1>
    <p class="page-desc">Slip gaji dari periode yang sudah final. PDF dilindungi password tanggal lahir (DDMMYYYY).</p>
</div>

@if ($payslips->isEmpty())
    <div class="card"><div class="empty-state"><span class="material-symbols-outlined">receipt_long</span>Belum ada payslip yang diterbitkan untuk Anda.</div></div>
@else
<div class="row g-3">
    <div class="col-lg-3">
        <div class="card">
            <div class="list-group list-group-flush">
                @foreach ($payslips as $p)
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center slip-item {{ $p->id === $selected->id ? 'active-slip' : '' }}" data-id="{{ $p->id }}">
                        <span><b>{{ $p->register->period->name }}</b><div class="cell-sub">{{ $p->payslip_no }}</div></span>
                        <span class="fs-xs fw-600 text-primary-hc">@rupiah($p->register->net_salary)</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="d-flex justify-content-end mb-2"><a class="btn btn-primary btn-sm" id="btnDownload" href="{{ route('payslips.pdf', $selected) }}"><span class="material-symbols-outlined ms-sm">download</span>Unduh PDF</a></div>
        <div id="slipPreview"></div>
    </div>
</div>
@endif
@endsection

@push('styles')
<style>.active-slip { background: var(--hc-primary-light); border-left: 3px solid var(--hc-primary); }</style>
@endpush

@push('scripts')
@if ($payslips->isNotEmpty())
<script>
$(function () {
    const base = @json(url('payslips'));
    const load = (id) => {
        $('#slipPreview').html('<div class="card"><div class="empty-state"><span class="spinner-border text-success"></span></div></div>');
        $.get(`${base}/${id}/preview`).done((html) => $('#slipPreview').html(html));
        $('#btnDownload').attr('href', `${base}/${id}/pdf`);
    };
    load({{ $selected->id }});
    $('.slip-item').on('click', function (e) {
        e.preventDefault();
        $('.slip-item').removeClass('active-slip'); $(this).addClass('active-slip');
        load($(this).data('id'));
    });
});
</script>
@endif
@endpush
