@extends('layouts.app')

@section('title', 'Rekap Absensi')
@section('breadcrumb')
    <li class="breadcrumb-item">Absensi</li>
    <li class="breadcrumb-item active">Rekap Absensi & Pengajuan</li>
@endsection

@php
    $user = auth()->user();
    $present = ($stats['hadir']->total ?? 0) + ($stats['terlambat']->total ?? 0) + ($stats['dinas']->total ?? 0);
    $leaveCount = ($stats['izin']->total ?? 0) + ($stats['sakit']->total ?? 0) + ($stats['cuti']->total ?? 0);
    $canPickEmployee = $user->hasPermission('attendance.create');
    $tab = request('tab', 'daily');
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">Manajemen Kehadiran · <span class="accent">Enterprise Shift Engine</span></div>
        <h1 class="page-title">Rekap Absensi & Pengajuan Cuti/Lembur</h1>
        <p class="page-desc">Audit ketepatan waktu, integrasi log fingerprint, serta approval hierarki atasan → HR.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @can('attendance.request')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal"><span class="material-symbols-outlined">edit_calendar</span>Ajukan Cuti/Izin</button>
            <button class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#overtimeModal"><span class="material-symbols-outlined">more_time</span>Form Lembur</button>
        @endcan
        @can('attendance.create')
            <button class="btn btn-outline-secondary" id="btnManual"><span class="material-symbols-outlined">post_add</span>Input Manual</button>
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><span class="material-symbols-outlined">fingerprint</span>Import Log Fingerprint</button>
        @endcan
        @can('attendance.export')
            <a class="btn btn-outline-secondary" href="{{ route('attendance.export', ['month' => substr($date, 0, 7)]) }}"><span class="material-symbols-outlined">download</span>Export XLSX</a>
        @endcan
    </div>
</div>

<form class="filter-bar mb-3 row g-3 align-items-end mx-0" method="get" action="{{ route('attendance.index') }}">
    <div class="col-md-3">
        <label class="form-label">Tanggal Absensi</label>
        <input type="date" name="date" class="form-control" value="{{ $date }}" max="{{ now()->toDateString() }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Departemen / Divisi</label>
        <select name="department_id" class="form-select"><option value="">Semua Departemen</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Shift Kerja</label>
        <select name="shift_id" class="form-select"><option value="">Semua Shift</option>
            @foreach ($shifts as $s)<option value="{{ $s->id }}" @selected(request('shift_id') == $s->id)>{{ $s->label() }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><span class="material-symbols-outlined">filter_alt</span>Terapkan Filter</button>
        <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary" title="Reset"><span class="material-symbols-outlined">refresh</span></a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Total Hadir</span><span class="stat-icon green"><span class="material-symbols-outlined">badge</span></span></div>
            <div class="stat-value">{{ $present }} <small>/ {{ $activeCount }} Karyawan</small></div>
            <div class="stat-foot text-primary-hc fw-600"><span class="material-symbols-outlined ms-sm">check_circle</span> {{ $activeCount ? round($present / $activeCount * 100, 1) : 0 }}% Tingkat Kehadiran</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Terlambat Masuk</span><span class="stat-icon red"><span class="material-symbols-outlined">alarm_off</span></span></div>
            <div class="stat-value text-danger">{{ $stats['terlambat']->total ?? 0 }} <small>Karyawan</small></div>
            <div class="stat-foot">Rata-rata delay: <b class="text-danger">{{ round($stats['terlambat']->avg_late ?? 0) }} Menit</b></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Izin / Sakit / Cuti</span><span class="stat-icon slate"><span class="material-symbols-outlined">flight_takeoff</span></span></div>
            <div class="stat-value">{{ $leaveCount }} <small>Karyawan</small></div>
            <div class="stat-foot">{{ $stats['sakit']->total ?? 0 }} Sakit, {{ $stats['izin']->total ?? 0 }} Izin, {{ $stats['cuti']->total ?? 0 }} Cuti · {{ $stats['alpha']->total ?? 0 }} Alpha</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between"><span class="stat-label">Pending Approval</span><span class="stat-icon green"><span class="material-symbols-outlined">pending_actions</span></span></div>
            <div class="stat-value">{{ $pendingLeave + $pendingOvertime }} <small>Permohonan</small></div>
            <div class="stat-foot d-flex gap-2"><span class="badge-soft slate">{{ $pendingLeave }} Cuti</span><span class="badge-soft slate">{{ $pendingOvertime }} Lembur</span></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="nav-tabs-hc d-flex flex-wrap align-items-center justify-content-between">
        <ul class="nav" role="tablist">
            <li class="nav-item"><button class="nav-link {{ $tab === 'daily' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabDaily" data-tab="daily" type="button"><span class="material-symbols-outlined ms-sm">fact_check</span>Rekap Absensi Harian <span class="count-pill green" id="countDaily">…</span></button></li>
            <li class="nav-item"><button class="nav-link {{ $tab === 'leave' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabLeave" data-tab="leave" type="button"><span class="material-symbols-outlined ms-sm">edit_calendar</span>Permohonan Cuti & Izin <span class="count-pill red">{{ $pendingLeave }} Pending</span></button></li>
            <li class="nav-item"><button class="nav-link {{ $tab === 'overtime' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabOvertime" data-tab="overtime" type="button"><span class="material-symbols-outlined ms-sm">more_time</span>Pengajuan Lembur <span class="count-pill">{{ $pendingOvertime }} Pending</span></button></li>
        </ul>
        <div class="input-icon py-2" style="min-width:240px"><span class="material-symbols-outlined">search</span>
            <input type="search" class="form-control" id="tabSearch" placeholder="Cari nama / NIK...">
        </div>
    </div>
    <div class="tab-content">
        <div class="tab-pane fade {{ $tab === 'daily' ? 'show active' : '' }}" id="tabDaily">
            <form id="dailyFilter">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                <input type="hidden" name="shift_id" value="{{ request('shift_id') }}">
                <input type="hidden" name="q" class="q-mirror">
            </form>
            <div class="table-responsive">
                <table class="table table-hc table-hover">
                    <thead><tr><th>Karyawan</th><th>Departemen</th><th>Shift Kerja</th><th>Jam Masuk (In)</th><th>Jam Pulang (Out)</th><th>Keterlambatan</th><th>Status Kehadiran</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody id="dailyRows"></tbody>
                </table>
            </div>
            <div class="table-footer" id="dailyFooter"></div>
        </div>
        <div class="tab-pane fade {{ $tab === 'leave' ? 'show active' : '' }}" id="tabLeave">
            <form id="leaveFilter" class="px-3 pt-3 d-flex gap-2">
                <input type="hidden" name="q" class="q-mirror">
                <select name="status" class="form-select" style="max-width:220px"><option value="">Semua Status</option><option value="pending">Menunggu Approval</option><option value="approved_manager">Disetujui Atasan</option><option value="approved">Disetujui</option><option value="rejected">Ditolak</option></select>
            </form>
            <div class="table-responsive">
                <table class="table table-hc table-hover">
                    <thead><tr><th>Karyawan</th><th>Jenis</th><th>Periode</th><th>Alasan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody id="leaveRows"></tbody>
                </table>
            </div>
            <div class="table-footer" id="leaveFooter"></div>
        </div>
        <div class="tab-pane fade {{ $tab === 'overtime' ? 'show active' : '' }}" id="tabOvertime">
            <form id="overtimeFilter" class="px-3 pt-3 d-flex gap-2">
                <input type="hidden" name="q" class="q-mirror">
                <select name="status" class="form-select" style="max-width:220px"><option value="">Semua Status</option><option value="pending">Menunggu Approval</option><option value="approved">Disetujui</option><option value="rejected">Ditolak</option></select>
            </form>
            <div class="table-responsive">
                <table class="table table-hc table-hover">
                    <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Durasi</th><th>Keterangan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody id="overtimeRows"></tbody>
                </table>
            </div>
            <div class="table-footer" id="overtimeFooter"></div>
        </div>
    </div>
</div>

{{-- Modal: Ajukan cuti/izin --}}
@can('attendance.request')
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="leaveForm" action="{{ route('leave-requests.store') }}" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">edit_calendar</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Formulir Pengajuan Cuti / Izin</h5><p class="modal-sub">Pengajuan diteruskan ke atasan langsung lalu HR untuk persetujuan.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Karyawan <span class="req">*</span></label>
                        @if ($canPickEmployee)
                            <select name="employee_id" class="form-select"><option value="">Pilih karyawan</option>
                                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($self?->id === $e->id)>{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $self?->full_name }} ({{ $self?->employee_no }})" readonly>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Cuti / Izin <span class="req">*</span></label>
                        <select name="leave_type_id" class="form-select">
                            @foreach ($leaveTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}{{ $t->default_quota ? " (kuota {$t->default_quota} hari)" : '' }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Tanggal Mulai <span class="req">*</span></label><input type="date" name="start_date" class="form-control" min="{{ now()->toDateString() }}"></div>
                    <div class="col-md-6"><label class="form-label">Tanggal Selesai <span class="req">*</span></label><input type="date" name="end_date" class="form-control" min="{{ now()->toDateString() }}"></div>
                    <div class="col-12"><label class="form-label">Alasan / Keterangan <span class="req">*</span></label><textarea name="reason" class="form-control" rows="3" placeholder="Jelaskan keperluan cuti/izin"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="hint"><span class="material-symbols-outlined ms-sm">info</span>Hari kerja dihitung otomatis (Sabtu/Minggu dikecualikan)</span>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">send</span>Kirim Pengajuan</button></div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="overtimeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="overtimeForm" action="{{ route('overtime-requests.store') }}" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">more_time</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Form Pengajuan Lembur</h5><p class="modal-sub">Lembur disetujui akan dihitung otomatis di register gaji (1/173 × upah).</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if ($canPickEmployee)
                    <div class="mb-3"><label class="form-label">Karyawan <span class="req">*</span></label>
                        <select name="employee_id" class="form-select"><option value="">Pilih karyawan</option>
                            @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($self?->id === $e->id)>{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach
                        </select></div>
                @endif
                <div class="row g-3">
                    <div class="col-7"><label class="form-label">Tanggal Lembur <span class="req">*</span></label><input type="date" name="overtime_date" class="form-control" value="{{ now()->toDateString() }}"></div>
                    <div class="col-5"><label class="form-label">Durasi (jam) <span class="req">*</span></label><input type="number" step="0.5" min="0.5" max="12" name="hours" class="form-control" value="2"></div>
                    <div class="col-12"><label class="form-label">Uraian Pekerjaan <span class="req">*</span></label><textarea name="reason" class="form-control" rows="3"></textarea></div>
                </div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">send</span>Ajukan Lembur</button>
            </div>
        </form>
    </div>
</div>
@endcan

{{-- Modal: input manual / koreksi --}}
@canany(['attendance.create', 'attendance.update'])
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="attendanceForm" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">edit_note</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Koreksi Absensi</h5><p class="modal-sub att-sub"></p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 create-only">
                        <label class="form-label">Karyawan <span class="req">*</span></label>
                        <select name="employee_id" class="form-select"><option value="">Pilih karyawan</option>
                            @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12 create-only"><label class="form-label">Tanggal <span class="req">*</span></label><input type="date" name="attendance_date" class="form-control" value="{{ $date }}" max="{{ now()->toDateString() }}"></div>
                    <div class="col-12"><label class="form-label">Shift <span class="req">*</span></label>
                        <select name="shift_id" class="form-select">@foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->label() }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label">Jam Masuk</label><input type="time" name="check_in" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Jam Pulang</label><input type="time" name="check_out" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Status <span class="req">*</span></label>
                        <select name="status" class="form-select">@foreach (\App\Models\Attendance::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                        <div class="form-text fs-xs">Status Hadir/Terlambat dihitung otomatis dari jam masuk & toleransi shift.</div></div>
                    <div class="col-12"><label class="form-label">Alasan Koreksi / Catatan <span class="req edit-only">*</span></label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan</button>
            </div>
        </form>
    </div>
</div>
@endcanany

@can('attendance.create')
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="importForm" action="{{ route('attendance.import') }}" method="post" enctype="multipart/form-data">
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">fingerprint</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Import Log Fingerprint / XLSX</h5><p class="modal-sub">Data yang sudah ada pada tanggal yang sama akan diperbarui.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Shift</label><select name="shift_id" class="form-select">@foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->label() }}</option>@endforeach</select></div>
                <input type="file" name="file" class="form-control mb-2" accept=".xlsx,.xls,.csv">
                <div class="fs-xs text-muted-hc">Kolom: <span class="mono">nik_karyawan, tanggal, jam_masuk, jam_pulang</span></div>
                <ul class="import-errors text-danger fs-xs mt-2 mb-0"></ul>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">upload</span>Import</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection

@push('scripts')
<script>
$(function () {
    const canUpdate = @json($user->hasPermission('attendance.update'));
    const person = (r) => `<div class="d-flex align-items-center gap-2">${HCMS.avatar(r.full_name)}
        <div><div class="cell-title">${HCMS.esc(r.full_name)}</div><div class="cell-sub">${HCMS.esc(r.employee_no)}</div></div></div>`;

    const daily = new HCMS.Table({
        url: @json(route('attendance.index')), tbody: '#dailyRows', footer: '#dailyFooter', filters: '#dailyFilter', colspan: 8,
        emptyText: 'Belum ada data absensi pada tanggal ini.',
        onLoad: (res) => $('#countDaily').text(res.total),
        render: (r) => {
            const late = r.status === 'terlambat';
            const srcIcon = { fingerprint: 'fingerprint', gps: 'location_on', manual: 'edit', import: 'upload_file' }[r.source];
            return `<tr class="${late ? 'row-warning' : ''}">
                <td>${person(r)}</td>
                <td>${HCMS.esc(r.department || '-')}</td>
                <td class="fs-sm">${HCMS.esc(r.shift || '-')}</td>
                <td class="${late ? 'text-danger fw-600' : 'fw-600'}">${r.check_in ? `<span class="material-symbols-outlined ms-sm me-1 ${late ? 'text-danger' : 'text-primary-hc'}">${late ? 'schedule' : srcIcon}</span>${r.check_in}` : '<span class="text-muted-hc">--:--</span>'}</td>
                <td>${r.check_out || '<span class="text-muted-hc">--:--</span>'}</td>
                <td>${r.late_minutes ? `<span class="badge-soft danger">+${r.late_minutes} mnt</span>` : `<span class="text-muted-hc fs-sm">${r.check_in ? '- (Tepat Waktu)' : '-'}</span>`}</td>
                <td>${HCMS.badge(r.status)}${r.notes ? `<div class="cell-sub mt-1">${HCMS.esc(r.notes)}</div>` : ''}</td>
                <td class="text-end">${canUpdate ? `<button class="btn-icon btn-correct" data-row='${JSON.stringify(r).replace(/'/g, '&#39;')}' title="Koreksi"><span class="material-symbols-outlined">edit_calendar</span></button>` : ''}</td>
            </tr>`;
        }
    });

    const decideButtons = (base, r) => r.can_decide ? `
        <button class="btn btn-sm btn-outline-secondary btn-decide" data-url="${base}/${r.id}/reject" data-confirm="Tolak pengajuan ${HCMS.esc(r.full_name)}?">Tolak</button>
        <button class="btn btn-sm btn-primary btn-decide" data-url="${base}/${r.id}/approve">Approve</button>` :
        (r.approver ? `<span class="cell-sub">oleh ${HCMS.esc(r.approver)}</span>` : '');

    const leave = new HCMS.Table({
        url: @json(route('leave-requests.index')), tbody: '#leaveRows', footer: '#leaveFooter', filters: '#leaveFilter', colspan: 6,
        render: (r) => `<tr>
            <td>${person(r)}</td>
            <td class="fw-600">${HCMS.esc(r.leave_type)}</td>
            <td>${r.start_date} – ${r.end_date}<div class="cell-sub">${r.total_days} hari kerja</div></td>
            <td class="fs-sm" style="max-width:260px">${HCMS.esc(r.reason || '-')}</td>
            <td>${HCMS.badge(r.status)}</td>
            <td class="text-end text-nowrap">${decideButtons(@json(url('leave-requests')), r)}</td></tr>`
    });

    const overtime = new HCMS.Table({
        url: @json(route('overtime-requests.index')), tbody: '#overtimeRows', footer: '#overtimeFooter', filters: '#overtimeFilter', colspan: 6,
        render: (r) => `<tr>
            <td>${person(r)}</td>
            <td>${r.overtime_date}</td>
            <td class="fw-600">${r.hours} jam</td>
            <td class="fs-sm" style="max-width:260px">${HCMS.esc(r.reason || '-')}</td>
            <td>${HCMS.badge(r.status)}</td>
            <td class="text-end text-nowrap">${decideButtons(@json(url('overtime-requests')), r)}</td></tr>`
    });

    const tables = { daily, leave, overtime };
    const loaded = {};
    let current = @json($tab);
    const loadTab = (t) => { tables[t].load(1); loaded[t] = true; };
    loadTab(current);
    $('[data-bs-toggle="tab"][data-tab]').on('shown.bs.tab', function () {
        current = $(this).data('tab');
        $('.q-mirror').val($('#tabSearch').val());
        if (!loaded[current]) loadTab(current);
    });

    // Pencarian di bar tab diteruskan ke tabel aktif.
    let timer;
    $('#tabSearch').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => { $('.q-mirror').val(this.value); tables[current].load(1); }, 350);
    });

    $(document).on('click', '.btn-decide', async function () {
        const $b = $(this);
        HCMS.action($b.data('url'), 'POST', {
            confirm: $b.data('confirm'), danger: !!$b.data('confirm'),
            onSuccess: () => tables[current].reload()
        });
    });

    // Form pengajuan
    [['#leaveForm', '#leaveModal', 'leave'], ['#overtimeForm', '#overtimeModal', 'overtime']].forEach(([f, m, t]) => {
        $(f).on('submit', function (e) {
            e.preventDefault();
            HCMS.submitForm($(this), { onSuccess: () => { bootstrap.Modal.getInstance($(m)[0]).hide(); this.reset(); if (loaded[t]) tables[t].reload(); } });
        });
    });
    @if (request('create')) $(() => bootstrap.Modal.getOrCreateInstance($('#leaveModal')[0])?.show()); @endif

    // Input manual & koreksi
    const $af = $('#attendanceForm');
    const attModal = $af.length ? bootstrap.Modal.getOrCreateInstance($('#attendanceModal')[0]) : null;
    $('#btnManual').on('click', function () {
        HCMS.resetForm($af);
        $af.attr('action', @json(route('attendance.store'))).attr('data-method', 'POST');
        $af.find('.create-only').removeClass('d-none'); $af.find('.edit-only').addClass('d-none');
        $('#attendanceModal .modal-title').text('Input Absensi Manual'); $('#attendanceModal .att-sub').text('');
        attModal.show();
    });
    $(document).on('click', '.btn-correct', function () {
        const r = $(this).data('row');
        HCMS.resetForm($af);
        HCMS.fillForm($af, { shift_id: r.shift_id, check_in: r.check_in, check_out: r.check_out, status: r.status, notes: '' });
        $af.attr('action', `${@json(url('attendance'))}/${r.id}`).attr('data-method', 'PUT');
        $af.find('.create-only').addClass('d-none'); $af.find('.edit-only').removeClass('d-none');
        $('#attendanceModal .modal-title').text('Koreksi Absensi');
        $('#attendanceModal .att-sub').text(`${r.full_name} · ${r.date}`);
        attModal.show();
    });
    $af.on('submit', (e) => { e.preventDefault(); HCMS.submitForm($af, { onSuccess: () => { attModal.hide(); daily.reload(); } }); });

    $('#importForm').on('submit', function (e) {
        e.preventDefault();
        const $f = $(this);
        HCMS.submitForm($f, { onSuccess: (res) => {
            $f.find('.import-errors').html((res.errors_detail || []).slice(0, 10).map((m) => `<li>${HCMS.esc(m)}</li>`).join(''));
            daily.reload();
        } });
    });
});
</script>
@endpush
