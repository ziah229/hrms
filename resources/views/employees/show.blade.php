@extends('layouts.app')

@section('title', $employee->full_name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Data Karyawan</a></li>
    <li class="breadcrumb-item active">{{ $employee->employee_no }}</li>
@endsection

@section('content')
@php
    $mask = fn ($v) => $v ? ($sensitive ? $v : str_repeat('•', max(strlen($v) - 4, 0)) . substr($v, -4)) : '-';
    $field = fn ($label, $value) => '<div class="col-sm-6 col-lg-4"><div class="fs-xs text-muted-hc text-uppercase fw-600" style="letter-spacing:.05em">' . e($label) . '</div><div class="fw-500">' . e($value ?: '-') . '</div></div>';
@endphp
<div class="card mb-3">
    <div class="card-body p-4 d-flex flex-wrap gap-3 align-items-center">
        @if ($employee->photo)
            <img src="{{ asset('storage/' . $employee->photo) }}" class="rounded-circle" width="64" height="64" style="object-fit:cover" alt="">
        @else
            <span class="avatar lg" style="width:64px;height:64px;font-size:1.1rem">{{ $employee->initials() }}</span>
        @endif
        <div class="flex-grow-1">
            <div class="code-link">{{ $employee->employee_no }}</div>
            <h1 class="page-title my-0">{{ $employee->full_name }}</h1>
            <div class="page-desc">{{ $employee->position?->name }} · {{ $employee->department?->name }} · {{ $employee->workLocation?->name }}</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge-soft {{ $employee->employment_status === 'tetap' ? 'success' : 'warning' }}">{{ \App\Models\Employee::EMPLOYMENT_STATUSES[$employee->employment_status] }}</span>
            {!! $employee->is_active ? '<span class="badge-soft dot success">Aktif</span>' : '<span class="badge-soft slate">Resign ' . $employee->resign_date?->translatedFormat('d M Y') . '</span>' !!}
        </div>
    </div>
    <ul class="nav nav-tabs-hc" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProfil" type="button"><span class="material-symbols-outlined ms-sm">badge</span>Profil</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKontrak" type="button"><span class="material-symbols-outlined ms-sm">contract</span>Kontrak <span class="count-pill">{{ $employee->contracts->count() }}</span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBank" type="button"><span class="material-symbols-outlined ms-sm">account_balance</span>Bank & BPJS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKeluarga" type="button"><span class="material-symbols-outlined ms-sm">family_restroom</span>Keluarga <span class="count-pill">{{ $employee->families->count() }}</span></button></li>
    </ul>
    <div class="tab-content p-4">
        <div class="tab-pane fade show active" id="tabProfil">
            <div class="form-section">Identitas</div>
            <div class="row g-3 mb-4">
                {!! $field('NIK KTP', $mask($employee->nik_ktp)) !!}
                {!! $field('NPWP', $mask($employee->npwp)) !!}
                {!! $field('Jenis Kelamin', $employee->gender === 'L' ? 'Laki-laki' : 'Perempuan') !!}
                {!! $field('Tempat, Tanggal Lahir', trim($employee->birth_place . ', ' . $employee->birth_date?->translatedFormat('d F Y'), ', ')) !!}
                {!! $field('Agama', $employee->religion) !!}
                {!! $field('Status Nikah / PTKP', $employee->marital_status . ' · ' . $employee->ptkp_status) !!}
                {!! $field('Email', $employee->email) !!}
                {!! $field('Telepon', $employee->phone) !!}
                {!! $field('Alamat', $employee->address) !!}
            </div>
            <div class="form-section">Kepegawaian</div>
            <div class="row g-3">
                {!! $field('Departemen', $employee->department?->name) !!}
                {!! $field('Jabatan', $employee->position?->name) !!}
                {!! $field('Atasan Langsung', $employee->manager?->full_name) !!}
                {!! $field('Tanggal Masuk', $employee->join_date?->translatedFormat('d F Y')) !!}
                {!! $field('Masa Kerja', $employee->join_date?->diffForHumans(null, true)) !!}
                {!! $field('Akun Sistem', $employee->user ? $employee->user->email . ' (' . $employee->user->role?->name . ')' : 'Belum memiliki akun') !!}
                @if ($salary)
                    {!! $field('Gaji Pokok Berlaku', 'Rp ' . number_format($salary->basic_salary, 0, ',', '.') . ' (sejak ' . $salary->effective_date->translatedFormat('d M Y') . ')') !!}
                @endif
            </div>
        </div>
        <div class="tab-pane fade" id="tabKontrak">
            <table class="table table-hc">
                <thead><tr><th>Jenis</th><th>Mulai</th><th>Selesai</th><th>Sisa</th></tr></thead>
                <tbody>
                @forelse ($employee->contracts->sortByDesc('start_date') as $c)
                    <tr><td>{{ $c->contract_type }}</td><td>{{ $c->start_date->translatedFormat('d M Y') }}</td><td>{{ $c->end_date?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td>@if ($c->end_date){!! $c->end_date->isPast() ? '<span class="badge-soft slate">Berakhir</span>' : '<span class="badge-soft ' . ($c->end_date->diffInDays() <= 30 ? 'danger' : 'success') . '">' . (int) now()->diffInDays($c->end_date) . ' hari</span>' !!}@endif</td></tr>
                @empty
                    <tr><td colspan="4" class="text-muted-hc">Karyawan tetap (PKWTT) — tidak ada kontrak berjangka.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="tab-pane fade" id="tabBank">
            <div class="row g-3">
                {!! $field('Bank', $employee->primaryBank?->bank_name) !!}
                {!! $field('No. Rekening', $mask($employee->primaryBank?->account_no)) !!}
                {!! $field('Atas Nama', $employee->primaryBank?->account_name) !!}
                {!! $field('BPJS Kesehatan', $employee->bpjs_kesehatan_no) !!}
                {!! $field('BPJS Ketenagakerjaan', $employee->bpjs_tk_no) !!}
            </div>
        </div>
        <div class="tab-pane fade" id="tabKeluarga">
            <table class="table table-hc">
                <thead><tr><th>Nama</th><th>Hubungan</th><th>Tanggal Lahir</th></tr></thead>
                <tbody>
                @forelse ($employee->families as $f)
                    <tr><td>{{ $f->name }}</td><td>{{ $f->relationship }}</td><td>{{ $f->birth_date?->translatedFormat('d M Y') }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted-hc">Belum ada data keluarga.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
