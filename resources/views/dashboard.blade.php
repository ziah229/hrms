@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@php
    $user = auth()->user();
    $total = max($activeCount, 1);
    $attRate = $presentToday ? round($presentToday / $total * 100, 1) : 0;
    $pendingTotal = $pendingLeaves->count() + $pendingOvertimes->count();
    $comp = ['tetap' => ['Karyawan Tetap (PKWTT)', '#059669'], 'kontrak' => ['Kontrak (PKWT)', '#34d399'], 'magang' => ['Magang / Internship', '#94a3b8']];
    $acc = 0;
    $stops = [];
    foreach ($comp as $key => [$label, $color]) {
        $pct = ($composition[$key] ?? 0) / $total * 100;
        $stops[] = "{$color} {$acc}% " . ($acc + $pct) . '%';
        $acc += $pct;
    }
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">Operasional HCMS · <span class="accent">Tahun Anggaran {{ now()->year }}</span></div>
        <h1 class="page-title">Ringkasan Eksekutif Kepegawaian</h1>
        <p class="page-desc">Monitoring metrik kehadiran, kepatuhan kontrak, dan siklus register gaji.</p>
    </div>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><span class="material-symbols-outlined">add_circle</span>Input Cepat</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @can('employee.create')<li><a class="dropdown-item" href="{{ route('employees.index', ['create' => 1]) }}">Tambah Karyawan</a></li>@endcan
                @can('attendance.request')<li><a class="dropdown-item" href="{{ route('attendance.index', ['tab' => 'leave', 'create' => 1]) }}">Ajukan Cuti / Izin</a></li>@endcan
                @can('component.create')<li><a class="dropdown-item" href="{{ route('components.index', ['tab' => 'adhoc']) }}">Input Bonus / Adhoc</a></li>@endcan
                @can('payroll.process')<li><a class="dropdown-item" href="{{ route('payroll.index') }}">Proses Gaji</a></li>@endcan
            </ul>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary" title="Muat ulang"><span class="material-symbols-outlined">refresh</span></a>
    </div>
</div>

{{-- Pengingat --}}
<div class="row g-3 mb-3">
    @if ($expiringContracts->isNotEmpty())
        <div class="col-lg-6">
            <div class="card alert-card h-100">
                <span class="stat-icon red"><span class="material-symbols-outlined">priority_high</span></span>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between gap-2"><h6>Peringatan Berakhirnya Masa Kontrak</h6><span class="badge-soft danger">Tindakan Diperlukan</span></div>
                    <p>Sebanyak <b>{{ $expiringContracts->count() }} karyawan</b> akan mencapai batas kontrak dalam &lt; 30 hari:
                        {{ $expiringContracts->take(3)->map(fn ($c) => $c->employee?->full_name . ' (' . $c->end_date->translatedFormat('d M') . ')')->join(', ') }}{{ $expiringContracts->count() > 3 ? ', ...' : '' }}</p>
                    <a href="{{ route('employees.index', ['status' => 'kontrak']) }}" class="fs-sm fw-600 text-decoration-none">Buka Daftar PKWT <span class="material-symbols-outlined ms-sm">arrow_forward</span></a>
                </div>
            </div>
        </div>
    @endif
    @if ($openPeriod)
        <div class="col-lg-6">
            <div class="card alert-card h-100">
                <span class="stat-icon green"><span class="material-symbols-outlined">account_balance</span></span>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between gap-2"><h6>Batch Payroll {{ $openPeriod->name }} Belum Final</h6><span class="badge-soft success">{{ \App\Models\PayrollPeriod::STATUSES[$openPeriod->status] }}</span></div>
                    <p>Perhitungan gaji, potongan BPJS & PPh 21 TER telah diproses. Menunggu review, approval, dan finalisasi periode.</p>
                    @can('payroll.view')<a href="{{ route('payroll.index', ['period' => $openPeriod->id]) }}" class="fs-sm fw-600 text-decoration-none">Buka Register Gaji <span class="material-symbols-outlined ms-sm">arrow_forward</span></a>@endcan
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Statistik --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Total Karyawan Aktif</span><span class="stat-icon green"><span class="material-symbols-outlined">badge</span></span></div>
            <div class="stat-value">{{ $activeCount }} <small>orang</small></div>
            <div class="stat-foot"><span class="badge-soft success">+{{ $newCount }} baru</span> · {{ $resignCount }} resign bulan ini</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Kehadiran Hari Ini</span><span class="stat-icon green"><span class="material-symbols-outlined">how_to_reg</span></span></div>
            <div class="stat-value">{{ $attRate }}% <small>/ {{ $activeCount }} staf</small></div>
            <div class="progress my-2"><div class="progress-bar" style="width: {{ $attRate }}%"></div></div>
            <div class="stat-foot mt-0">{{ $presentToday }} Hadir · {{ $todayStats['terlambat'] ?? 0 }} Terlambat · {{ ($todayStats['izin'] ?? 0) + ($todayStats['sakit'] ?? 0) + ($todayStats['cuti'] ?? 0) }} Izin/Cuti · {{ $todayStats['alpha'] ?? 0 }} Alpha</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Payroll {{ $lastPeriod?->name ?? '-' }}</span><span class="stat-icon slate"><span class="material-symbols-outlined">account_balance_wallet</span></span></div>
            @if (! is_null($payrollCost))
                <div class="stat-value" style="font-size:1.5rem">Rp {{ number_format($payrollCost / 1_000_000, 1, ',', '.') }} Jt</div>
                <div class="stat-foot"><span class="badge-soft success"><span class="material-symbols-outlined ms-sm">lock</span>Final & Locked</span> Beban kas netto</div>
            @else
                <div class="stat-value" style="font-size:1.5rem">—</div>
                <div class="stat-foot">Data gaji hanya untuk role terkait.</div>
            @endif
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Tindakan Menunggu</span><span class="stat-icon red"><span class="material-symbols-outlined">pending_actions</span></span></div>
            <div class="stat-value text-danger">{{ $pendingTotal + $expiringContracts->count() + ($openPeriod ? 1 : 0) }} <small>antrean</small></div>
            <div class="stat-foot">{{ $expiringContracts->count() }} Kontrak · {{ $pendingTotal }} Pengajuan · {{ $openPeriod ? 1 : 0 }} Payroll</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="card-title-hc">Tren Kehadiran Harian</h5>
                        <p class="card-sub">5 hari kerja terakhir</p>
                    </div>
                    <div class="fs-sm d-flex gap-3 align-items-center">
                        <span><span class="legend-dot" style="background:#059669"></span>Tepat Waktu</span>
                        <span><span class="legend-dot" style="background:#cbd5e1"></span>Terlambat</span>
                        @can('attendance.export')<a href="{{ route('attendance.export') }}" class="btn btn-sm btn-outline-secondary">Unduh XLSX</a>@endcan
                    </div>
                </div>
                <div class="bar-chart">
                    @foreach ($trend as $day)
                        @php $sum = $day->ontime + $day->late; @endphp
                        <div class="bar-col">
                            <div class="bar-stack" style="height: {{ $sum / $total * 85 }}%">
                                <div class="bar-late" style="height: {{ $sum ? $day->late / $sum * 100 : 0 }}%"></div>
                                <div class="bar-ontime flex-grow-1"></div>
                            </div>
                            <div class="bar-label">{{ \Carbon\Carbon::parse($day->attendance_date)->translatedFormat('D') }}<small>{{ $sum }}/{{ $activeCount }}</small></div>
                        </div>
                    @endforeach
                </div>
                @php $mt = max($monthAtt->total, 1); @endphp
                <div class="row g-2 mt-3">
                    <div class="col-6 col-md-3"><div class="mini-stat"><div class="lbl">Rata-rata On-Time</div><div class="val">{{ round($monthAtt->ontime / $mt * 100, 1) }}%</div></div></div>
                    <div class="col-6 col-md-3"><div class="mini-stat"><div class="lbl">Total Keterlambatan</div><div class="val">{{ (int) $monthAtt->late }} Kasus</div></div></div>
                    <div class="col-6 col-md-3"><div class="mini-stat"><div class="lbl">Pengajuan Lembur</div><div class="val">{{ $pendingOvertimes->count() }} Pending</div></div></div>
                    <div class="col-6 col-md-3"><div class="mini-stat"><div class="lbl">Tingkat Alpha</div><div class="val text-danger">{{ round($monthAtt->alpha / $mt * 100, 1) }}%</div></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-body p-4">
                <h5 class="card-title-hc">Komposisi Tenaga Kerja</h5>
                <p class="card-sub mb-4">Rasio ikatan kerja & status ketenagakerjaan</p>
                <div class="donut mb-4" style="background: conic-gradient({{ implode(', ', $stops) }})">
                    <div class="donut-center"><strong>{{ $activeCount }}</strong><span>Total Pegawai</span></div>
                </div>
                <div class="d-flex flex-column gap-2">
                    @foreach ($comp as $key => [$label, $color])
                        <div class="list-row">
                            <span class="legend-dot rounded-circle" style="background: {{ $color }}"></span>
                            <span class="flex-grow-1">{{ $label }}</span>
                            <b>{{ $composition[$key] ?? 0 }}</b><span class="text-muted-hc fs-xs">({{ round(($composition[$key] ?? 0) / $total * 100) }}%)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title-hc">Pengajuan Butuh Perhatian <span class="badge-soft danger ms-1">{{ $pendingTotal }} Antrean</span></h5>
                    <p class="card-sub">Cuti/izin dan lembur yang menanti persetujuan.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hc table-hover">
                    <thead><tr><th>Karyawan</th><th>Departemen</th><th>Pengajuan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    @forelse ($pendingLeaves->map(fn ($l) => ['type' => 'leave', 'm' => $l])->concat($pendingOvertimes->map(fn ($o) => ['type' => 'overtime', 'm' => $o])) as $row)
                        @php $m = $row['m']; @endphp
                        <tr id="pending-{{ $row['type'] }}-{{ $m->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar">{{ $m->employee->initials() }}</span>
                                    <div><div class="cell-title">{{ $m->employee->full_name }}</div><div class="cell-sub">{{ $m->employee->employee_no }}</div></div>
                                </div>
                            </td>
                            <td>{{ $m->employee->department?->name }}</td>
                            <td>
                                @if ($row['type'] === 'leave')
                                    <div class="fw-600">{{ $m->leaveType->name }}</div>
                                    <div class="cell-sub">{{ $m->start_date->translatedFormat('d M') }} – {{ $m->end_date->translatedFormat('d M Y') }} ({{ $m->total_days }} hari)</div>
                                @else
                                    <div class="fw-600">Klaim Lembur ({{ (float) $m->hours }} jam)</div>
                                    <div class="cell-sub">{{ $m->overtime_date->translatedFormat('d M Y') }} · {{ $m->reason }}</div>
                                @endif
                            </td>
                            <td><span class="badge-soft warning">Menunggu Approval</span></td>
                            <td class="text-end text-nowrap">
                                @can('attendance.approve')
                                    @php $base = $row['type'] === 'leave' ? 'leave-requests' : 'overtime-requests'; @endphp
                                    <button class="btn btn-sm btn-outline-secondary btn-decide" data-url="{{ url("$base/{$m->id}/reject") }}" data-row="#pending-{{ $row['type'] }}-{{ $m->id }}">Tolak</button>
                                    <button class="btn btn-sm btn-primary btn-decide" data-url="{{ url("$base/{$m->id}/approve") }}" data-row="#pending-{{ $row['type'] }}-{{ $m->id }}">Approve</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><span class="material-symbols-outlined">task_alt</span>Tidak ada pengajuan yang menunggu.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Menampilkan {{ $pendingTotal }} entitas prioritas</span>
                <a href="{{ route('attendance.index', ['tab' => 'leave']) }}" class="fw-600 text-decoration-none">Lihat seluruh pengajuan <span class="material-symbols-outlined ms-sm">arrow_forward</span></a>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body p-4">
                <h5 class="card-title-hc">Agenda Libur</h5>
                <p class="card-sub mb-3">Kalender operasional perusahaan</p>
                <div class="d-flex flex-column gap-2">
                    @forelse ($holidays as $h)
                        <div class="list-row align-items-start">
                            <div class="date-chip"><div class="m">{{ $h->holiday_date->translatedFormat('M') }}</div><div class="d">{{ $h->holiday_date->format('d') }}</div></div>
                            <div><div class="fw-600">{{ $h->name }}</div><div class="fs-xs text-danger fw-600">Libur Nasional</div><div class="fs-xs text-muted-hc">{{ $h->holiday_date->translatedFormat('l, d F Y') }}</div></div>
                        </div>
                    @empty
                        <div class="text-muted-hc fs-sm">Belum ada hari libur terjadwal.</div>
                    @endforelse
                </div>
                @can('setting.manage')
                    <a href="{{ route('master.index', 'holidays') }}" class="btn btn-outline-secondary w-100 mt-3"><span class="material-symbols-outlined">calendar_month</span>Atur Kalender Perusahaan</a>
                @endcan
            </div>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <h5 class="card-title-hc d-flex align-items-center gap-2"><span class="material-symbols-outlined text-primary-hc">verified_user</span>Pusat Validasi Pajak & BPJS</h5>
                <p class="card-sub mb-3">Parameter perhitungan aktif (config/payroll.php).</p>
                <div class="list-row mb-2 justify-content-between"><span>BPJS Kesehatan</span><b class="fs-xs">1% · cap Rp 12 Jt</b></div>
                <div class="list-row mb-2 justify-content-between"><span>BPJS TK (JHT + JP)</span><b class="fs-xs">2% + 1%</b></div>
                <div class="list-row justify-content-between"><span>PPh 21</span><b class="fs-xs text-primary-hc">TER PP 58/2023</b></div>
            </div>
        </div>
    </div>
</div>

@if ($lastActivity)
    <div class="card mt-3">
        <div class="card-body py-3 px-4 d-flex justify-content-between flex-wrap gap-2 fs-sm">
            <span class="d-flex align-items-center gap-2"><span class="material-symbols-outlined text-muted-hc">history</span>
                Aktivitas Terakhir: <b>{{ $lastActivity->user?->name ?? 'Sistem' }}</b> — {{ $lastActivity->action }} {{ $lastActivity->entity }} ({{ $lastActivity->created_at->translatedFormat('d M Y, H:i') }})</span>
            @can('audit.view')<a href="{{ route('settings.audit-logs') }}" class="fw-600 text-decoration-none">Buka Audit Trail Lengkap <span class="material-symbols-outlined ms-sm">arrow_forward</span></a>@endcan
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    $(document).on('click', '.btn-decide', function () {
        const $b = $(this);
        HCMS.action($b.data('url'), 'POST', {
            onSuccess: () => $($b.data('row')).fadeOut(250, function () { $(this).remove(); })
        });
    });
</script>
@endpush
