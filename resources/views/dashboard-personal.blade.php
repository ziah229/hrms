@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="mb-4">
    <div class="page-eyebrow">Self Service · <span class="accent">{{ now()->translatedFormat('F Y') }}</span></div>
    <h1 class="page-title">Halo, {{ $employee?->full_name ?? auth()->user()->name }}</h1>
    <p class="page-desc">{{ $employee?->position?->name }} · {{ $employee?->department?->name }}</p>
</div>

@if (! $employee)
    <div class="alert alert-warning">Akun Anda belum terhubung dengan data karyawan. Hubungi HR Admin.</div>
@else
<div class="row g-3 mb-3">
    @foreach ([['Hadir', ($attendance['hadir'] ?? 0) + ($attendance['dinas'] ?? 0), 'how_to_reg', 'green'], ['Terlambat', $attendance['terlambat'] ?? 0, 'alarm_off', 'red'], ['Izin / Sakit / Cuti', ($attendance['izin'] ?? 0) + ($attendance['sakit'] ?? 0) + ($attendance['cuti'] ?? 0), 'flight_takeoff', 'slate'], ['Alpha', $attendance['alpha'] ?? 0, 'event_busy', 'amber']] as [$label, $val, $icon, $color])
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card">
                <div class="d-flex justify-content-between"><span class="stat-label">{{ $label }} (bulan ini)</span><span class="stat-icon {{ $color }}"><span class="material-symbols-outlined">{{ $icon }}</span></span></div>
                <div class="stat-value">{{ $val }} <small>hari</small></div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card stat-card dark">
            <div class="stat-label">Take Home Pay Terakhir</div>
            <div class="stat-value">{{ $latestRegister ? 'Rp ' . number_format($latestRegister->net_salary, 0, ',', '.') : '—' }}</div>
            <div class="stat-foot">{{ $latestRegister?->period->name ?? 'Belum ada payslip final' }}</div>
            @if ($latestRegister?->payslip)
                <div class="mt-3 d-flex gap-2">
                    <a href="{{ route('payslips.mine') }}" class="btn btn-sm btn-primary">Lihat Payslip</a>
                    <a href="{{ route('payslips.pdf', $latestRegister->payslip) }}" class="btn btn-sm btn-light">Unduh PDF</a>
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title-hc">Pengajuan Cuti Saya</h5>
                @can('attendance.request')<a href="{{ route('attendance.index', ['tab' => 'leave', 'create' => 1]) }}" class="btn btn-sm btn-primary">+ Ajukan</a>@endcan
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($leaves as $l)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><div class="fw-600">{{ $l->leaveType->name }}</div><div class="cell-sub">{{ $l->start_date->translatedFormat('d M') }} – {{ $l->end_date->translatedFormat('d M Y') }}</div></div>
                        @include('partials.status-badge', ['status' => $l->status])
                    </li>
                @empty
                    <li class="list-group-item text-muted-hc fs-sm">Belum ada pengajuan.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endif
@endsection
