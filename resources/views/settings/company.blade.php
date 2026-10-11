@extends('layouts.app')

@section('title', 'Pengaturan Perusahaan')
@section('breadcrumb')
    <li class="breadcrumb-item">Pengaturan</li>
    <li class="breadcrumb-item active">Perusahaan</li>
@endsection

@section('content')
<div class="mb-4">
    <div class="page-eyebrow">Pengaturan · <span class="accent">Profil Perusahaan</span></div>
    <h1 class="page-title">Pengaturan Perusahaan</h1>
    <p class="page-desc">Data ini tampil pada payslip dan dokumen PDF.</p>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form class="card" id="companyForm" action="{{ route('settings.company.update') }}" method="post" data-method="PUT" enctype="multipart/form-data" novalidate>
            <div class="card-body p-4 row g-3">
                <div class="col-12"><label class="form-label">Nama Perusahaan <span class="req">*</span></label><input type="text" name="name" class="form-control" value="{{ $company->name }}"></div>
                <div class="col-md-6"><label class="form-label">NPWP</label><input type="text" name="npwp" class="form-control" value="{{ $company->npwp }}"></div>
                <div class="col-md-6"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control" value="{{ $company->phone }}"></div>
                <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="3">{{ $company->address }}</textarea></div>
                <div class="col-12"><label class="form-label">Logo</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
            </div>
            <div class="modal-footer justify-content-end border-top"><button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan</button></div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body p-4">
            <h5 class="card-title-hc mb-2">Parameter Pajak & BPJS</h5>
            <p class="card-sub mb-3">Saat ini dikonfigurasi di <span class="mono">config/payroll.php</span>.</p>
            @php $b = config('payroll.bpjs'); @endphp
            <div class="list-row mb-2 justify-content-between"><span>BPJS Kesehatan</span><b>{{ $b['kesehatan_rate'] * 100 }}% · cap @rupiah($b['kesehatan_cap'])</b></div>
            <div class="list-row mb-2 justify-content-between"><span>JHT</span><b>{{ $b['jht_rate'] * 100 }}%</b></div>
            <div class="list-row mb-2 justify-content-between"><span>Jaminan Pensiun</span><b>{{ $b['jp_rate'] * 100 }}% · cap @rupiah($b['jp_cap'])</b></div>
            <div class="list-row justify-content-between"><span>Lembur</span><b>1/{{ config('payroll.overtime_divider') }} × {{ config('payroll.overtime_multiplier') }}</b></div>
        </div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$('#companyForm').on('submit', function (e) { e.preventDefault(); HCMS.submitForm($(this)); });
</script>
@endpush
