@extends('layouts.app')

@section('title', 'Allowances & Deductions')
@section('breadcrumb')
    <li class="breadcrumb-item">Kompensasi & Benefit</li>
    <li class="breadcrumb-item active">Allowances & Deductions</li>
@endsection

@php
    $user = auth()->user();
    $tab = request('tab', 'master');
    $methodIcon = ['nominal' => 'attach_money', 'percent' => 'percent', 'per_day' => 'calendar_today', 'formula' => 'functions'];
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">Kompensasi & Benefit · <span class="accent">Master Payroll</span></div>
        <h1 class="page-title">Master & Alokasi Komponen Gaji <span class="count-pill align-middle">{{ $counts['master'] }} Komponen</span></h1>
        <p class="page-desc">Kelola tunjangan tetap/variabel, potongan BPJS, PPh 21, komponen sekali bayar, dan kasbon karyawan.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @can('component.create')
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#loanModal"><span class="material-symbols-outlined">credit_card</span>Catat Kasbon</button>
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adhocModal"><span class="material-symbols-outlined">bolt</span>Input Sekali Bayar</button>
            <button class="btn btn-primary" id="btnCreateComponent"><span class="material-symbols-outlined">add_circle</span>Tambah Komponen Baru</button>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Total Komponen Aktif</span><span class="stat-icon green"><span class="material-symbols-outlined">account_tree</span></span></div><div class="stat-value">{{ $stats['active'] }} <small>Komponen</small></div><div class="stat-foot"><span class="text-primary-hc">● {{ $stats['allowance'] }} Tunjangan</span> &nbsp; <span class="text-danger">● {{ $stats['deduction'] }} Potongan</span></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Alokasi Tetap Bulanan</span><span class="stat-icon green"><span class="material-symbols-outlined">payments</span></span></div><div class="stat-value" style="font-size:1.4rem">@rupiah($stats['monthly'])</div><div class="stat-foot">{{ $stats['assignments'] }} assignment aktif</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Komponen Kena Pajak</span><span class="stat-icon green"><span class="material-symbols-outlined">fact_check</span></span></div><div class="stat-value">{{ $stats['taxable'] }} <small>Komponen</small></div><div class="stat-foot">Sesuai skema PPh 21 TER</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Pinjaman & Kasbon Berjalan</span><span class="stat-icon red"><span class="material-symbols-outlined">credit_card_off</span></span></div><div class="stat-value text-danger" style="font-size:1.4rem">@rupiah($stats['loans'])</div><div class="stat-foot">{{ $stats['loanCount'] }} pegawai aktif cicilan</div></div></div>
</div>

<div class="card">
    <ul class="nav nav-tabs-hc" role="tablist">
        <li class="nav-item"><button class="nav-link {{ $tab === 'master' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabMaster" data-tab="master" type="button"><span class="material-symbols-outlined ms-sm">list</span>Master Komponen <span class="count-pill green">{{ $counts['master'] }}</span></button></li>
        <li class="nav-item"><button class="nav-link {{ $tab === 'employee' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabEmployee" data-tab="employee" type="button"><span class="material-symbols-outlined ms-sm">badge</span>Komponen per Karyawan <span class="count-pill">{{ $counts['employee'] }}</span></button></li>
        <li class="nav-item"><button class="nav-link {{ $tab === 'adhoc' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabAdhoc" data-tab="adhoc" type="button"><span class="material-symbols-outlined ms-sm">bolt</span>Sekali Bayar (Ad-hoc) <span class="count-pill">{{ $counts['adhoc'] }}</span></button></li>
        <li class="nav-item"><button class="nav-link {{ $tab === 'loan' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabLoan" data-tab="loan" type="button"><span class="material-symbols-outlined ms-sm">credit_card</span>Kasbon & Pinjaman <span class="count-pill red">{{ $counts['loan'] }}</span></button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade {{ $tab === 'master' ? 'show active' : '' }}" id="tabMaster">
            <form class="p-3 row g-2 border-bottom" id="masterFilter">
                <div class="col-lg-4"><div class="input-icon"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari kode atau nama komponen (cth: ALW-TRN)"></div></div>
                <div class="col-sm-4 col-lg-2"><select name="type" class="form-select"><option value="">Semua Tipe</option><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select></div>
                <div class="col-sm-4 col-lg-2"><select name="nature" class="form-select"><option value="">Semua Sifat</option><option value="fixed">Tetap</option><option value="variable">Variabel</option></select></div>
                <div class="col-sm-4 col-lg-2"><select name="taxable" class="form-select"><option value="">Semua Status Pajak</option><option value="1">Taxable</option><option value="0">Non-Taxable</option></select></div>
            </form>
            <div class="table-responsive"><table class="table table-hc table-hover">
                <thead><tr><th>Kode & Nama Komponen</th><th>Sifat</th><th>Metode Hitung</th><th>Kepatuhan Pajak</th><th>Penerima</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody id="masterRows"></tbody>
            </table></div>
            <div class="table-footer" id="masterFooter"></div>
        </div>
        <div class="tab-pane fade {{ $tab === 'employee' ? 'show active' : '' }}" id="tabEmployee">
            <form class="p-3 row g-2 border-bottom" id="employeeFilter">
                <div class="col-lg-4"><div class="input-icon"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari nama / NIK karyawan"></div></div>
                <div class="col-sm-6 col-lg-3"><select name="pay_component_id" class="form-select"><option value="">Semua Komponen</option>@foreach ($components as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-lg-2"><select name="active" class="form-select"><option value="1">Masih berlaku</option><option value="0">Semua</option></select></div>
                <div class="col-lg-3 text-lg-end">@can('component.create')<button type="button" class="btn btn-light-primary" id="btnAssign"><span class="material-symbols-outlined">add</span>Assign Komponen</button>@endcan</div>
            </form>
            <div class="table-responsive"><table class="table table-hc table-hover">
                <thead><tr><th>Karyawan</th><th>Komponen</th><th class="text-end">Nilai</th><th>Periode Berlaku</th><th class="text-end">Aksi</th></tr></thead>
                <tbody id="employeeRows"></tbody>
            </table></div>
            <div class="table-footer" id="employeeFooter"></div>
        </div>
        <div class="tab-pane fade {{ $tab === 'adhoc' ? 'show active' : '' }}" id="tabAdhoc">
            <form class="p-3 row g-2 border-bottom" id="adhocFilter">
                <div class="col-lg-4"><div class="input-icon"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari nama karyawan"></div></div>
                <div class="col-sm-6 col-lg-3"><select name="payroll_period_id" class="form-select"><option value="">Semua Periode</option>@foreach (\App\Models\PayrollPeriod::orderByDesc('year')->orderByDesc('month')->get() as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
            </form>
            <div class="table-responsive"><table class="table table-hc table-hover">
                <thead><tr><th>Karyawan</th><th>Komponen</th><th>Periode</th><th class="text-end">Jumlah</th><th>Keterangan</th><th class="text-end">Aksi</th></tr></thead>
                <tbody id="adhocRows"></tbody>
            </table></div>
            <div class="table-footer" id="adhocFooter"></div>
        </div>
        <div class="tab-pane fade {{ $tab === 'loan' ? 'show active' : '' }}" id="tabLoan">
            <form class="p-3 border-bottom" id="loanFilter"><div class="input-icon" style="max-width:360px"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari nama karyawan"></div></form>
            <div class="table-responsive"><table class="table table-hc table-hover">
                <thead><tr><th>Karyawan</th><th class="text-end">Total Pinjaman</th><th class="text-end">Cicilan / Bulan</th><th>Progres</th><th class="text-end">Sisa</th><th>Mulai</th><th>Status</th></tr></thead>
                <tbody id="loanRows"></tbody>
            </table></div>
            <div class="table-footer" id="loanFooter"></div>
        </div>
    </div>
</div>

@include('pay-components.partials.modals')
@endsection

@push('scripts')
<script>
$(function () {
    const can = { update: @json($user->hasPermission('component.update')), del: @json($user->hasPermission('component.delete')) };
    const methods = @json($methods);
    const methodIcon = @json($methodIcon);
    const typeTag = (t) => t === 'allowance' ? '<span class="tag-type alw">ALW</span>' : '<span class="tag-type ded">DED</span>';
    const valueText = (method, v) => method === 'percent' ? `${v}%` : (method === 'per_day' ? `${HCMS.rupiah(v)} / hari` : (method === 'formula' ? (Number(v) ? `Parameter ${Number(v)}` : 'Rumus sistem') : HCMS.rupiah(v)));
    const json = (r) => JSON.stringify(r).replace(/'/g, '&#39;');
    const base = @json(url('components'));

    const tables = {
        master: new HCMS.Table({
            url: base, tbody: '#masterRows', footer: '#masterFooter', filters: '#masterFilter', colspan: 7,
            render: (r) => `<tr>
                <td><div class="d-flex gap-2 align-items-center">${typeTag(r.type)}
                    <div><div class="cell-title mono">${HCMS.esc(r.code)} <span class="badge-soft ${r.type === 'allowance' ? 'success' : 'danger'} ms-1">${r.type.toUpperCase()}</span></div><div class="cell-sub">${HCMS.esc(r.name)}</div></div></div></td>
                <td>${r.nature === 'fixed' ? 'Tetap (Bulanan)' : 'Variabel'}</td>
                <td><span class="material-symbols-outlined ms-sm text-muted-hc me-1">${methodIcon[r.calc_method]}</span>${methods[r.calc_method]}<div class="cell-sub">${valueText(r.calc_method, r.default_value)}</div></td>
                <td>${r.type === 'deduction' ? '<span class="badge-soft slate">Pengurang</span>' : (r.is_taxable ? '<span class="badge-soft success">Taxable (PPh 21)</span>' : '<span class="badge-soft slate">Non-Taxable</span>')}</td>
                <td>${r.calc_method === 'formula' ? '<span class="cell-sub">Otomatis oleh sistem</span>' : `${r.employee_count} Karyawan`}</td>
                <td>${r.is_active ? HCMS.badge('active') : HCMS.badge('inactive')}</td>
                <td class="text-end text-nowrap">
                    ${can.update ? `<button class="btn-icon btn-edit-component" data-row='${json(r)}' title="Edit"><span class="material-symbols-outlined">edit</span></button>` : ''}
                    ${can.del ? `<button class="btn-icon danger btn-delete" data-url="${base}/${r.id}" title="Hapus/Nonaktifkan"><span class="material-symbols-outlined">delete</span></button>` : ''}
                </td></tr>`
        }),
        employee: new HCMS.Table({
            url: base + '/employee', tbody: '#employeeRows', footer: '#employeeFooter', filters: '#employeeFilter', colspan: 5,
            render: (r) => `<tr>
                <td><div class="d-flex align-items-center gap-2">${HCMS.avatar(r.full_name)}<div><div class="cell-title">${HCMS.esc(r.full_name)}</div><div class="cell-sub">${HCMS.esc(r.employee_no)} · ${HCMS.esc(r.department || '')}</div></div></div></td>
                <td>${typeTag(r.type)} <span class="mono fs-sm fw-600">${HCMS.esc(r.code)}</span><div class="cell-sub">${HCMS.esc(r.component)}</div></td>
                <td class="text-num fw-600">${valueText(r.calc_method, r.value)}</td>
                <td>${r.start_date} – ${r.end_date || '<span class="badge-soft success">Berlanjut</span>'}</td>
                <td class="text-end text-nowrap">
                    ${can.update ? `<button class="btn-icon btn-edit-assign" data-row='${json(r)}' title="Edit"><span class="material-symbols-outlined">edit</span></button>` : ''}
                    ${can.del ? `<button class="btn-icon danger btn-delete" data-url="${base}/employee/${r.id}" title="Hapus"><span class="material-symbols-outlined">delete</span></button>` : ''}
                </td></tr>`
        }),
        adhoc: new HCMS.Table({
            url: base + '/adhoc', tbody: '#adhocRows', footer: '#adhocFooter', filters: '#adhocFilter', colspan: 6,
            render: (r) => `<tr>
                <td><div class="cell-title">${HCMS.esc(r.full_name)}</div><div class="cell-sub">${HCMS.esc(r.employee_no)}</div></td>
                <td>${typeTag(r.type)} ${HCMS.esc(r.component)}</td>
                <td>${HCMS.esc(r.period)} ${r.period_locked ? '<span class="material-symbols-outlined ms-sm text-muted-hc">lock</span>' : ''}</td>
                <td class="text-num fw-600 ${r.type === 'deduction' ? 'text-danger' : 'text-primary-hc'}">${r.type === 'deduction' ? '−' : '+'}${HCMS.rupiah(r.amount)}</td>
                <td class="fs-sm">${HCMS.esc(r.notes || '-')}</td>
                <td class="text-end">${can.del && !r.period_locked ? `<button class="btn-icon danger btn-delete" data-url="${base}/adhoc/${r.id}"><span class="material-symbols-outlined">delete</span></button>` : ''}</td></tr>`
        }),
        loan: new HCMS.Table({
            url: base + '/loans', tbody: '#loanRows', footer: '#loanFooter', filters: '#loanFilter', colspan: 7,
            render: (r) => {
                const pct = Math.round((1 - r.remaining_amount / r.total_amount) * 100);
                return `<tr>
                <td><div class="cell-title">${HCMS.esc(r.full_name)}</div><div class="cell-sub">${HCMS.esc(r.employee_no)}</div></td>
                <td class="text-num">${HCMS.rupiah(r.total_amount)}</td>
                <td class="text-num">${HCMS.rupiah(r.installment_amount)}</td>
                <td style="min-width:140px"><div class="progress mb-1"><div class="progress-bar" style="width:${pct}%"></div></div><div class="cell-sub">${r.paid_count}/${r.tenor_months} cicilan</div></td>
                <td class="text-num fw-600 text-danger">${HCMS.rupiah(r.remaining_amount)}</td>
                <td>${r.start_date}</td><td>${HCMS.badge(r.status)}</td></tr>`;
            }
        })
    };

    let current = @json($tab);
    const loaded = {};
    tables[current].load(); loaded[current] = true;
    $('[data-bs-toggle="tab"][data-tab]').on('shown.bs.tab', function () {
        current = $(this).data('tab');
        if (!loaded[current]) { tables[current].load(); loaded[current] = true; }
    });

    $(document).on('click', '.btn-delete', function () {
        HCMS.action($(this).data('url'), 'DELETE', { confirm: 'Hapus data ini?', danger: true, okText: 'Hapus', onSuccess: () => tables[current].reload() });
    });

    // Master komponen
    const $cf = $('#componentForm'), cModal = $cf.length ? bootstrap.Modal.getOrCreateInstance($('#componentModal')[0]) : null;
    $('#btnCreateComponent').on('click', () => {
        HCMS.resetForm($cf); $cf.attr('action', base).attr('data-method', 'POST');
        $cf.find('[name="code"]').prop('readonly', false);
        $('#componentModal .modal-title').text('Tambah Komponen Baru'); cModal.show();
    });
    $(document).on('click', '.btn-edit-component', function () {
        const r = $(this).data('row');
        HCMS.resetForm($cf); HCMS.fillForm($cf, r);
        $cf.attr('action', `${base}/${r.id}`).attr('data-method', 'PUT');
        $('#componentModal .modal-title').text('Edit Komponen ' + r.code); cModal.show();
    });
    $cf.on('submit', (e) => { e.preventDefault(); HCMS.submitForm($cf, { onSuccess: () => { cModal.hide(); tables.master.reload(); } }); });

    // Assign komponen ke karyawan
    const $af = $('#assignForm'), aModal = $af.length ? bootstrap.Modal.getOrCreateInstance($('#assignModal')[0]) : null;
    $('#btnAssign').on('click', () => { HCMS.resetForm($af); $af.attr('action', base + '/employee').attr('data-method', 'POST'); $af.find('select[name="employee_id"]').prop('disabled', false); aModal.show(); });
    $(document).on('click', '.btn-edit-assign', function () {
        const r = $(this).data('row');
        HCMS.resetForm($af); HCMS.fillForm($af, r);
        $af.attr('action', `${base}/employee/${r.id}`).attr('data-method', 'PUT'); aModal.show();
    });
    $af.on('change', '[name="pay_component_id"]', function () {
        const opt = $(this).find(':selected');
        if (!$af.find('[name="value"]').val()) $af.find('[name="value"]').val(opt.data('default'));
        $af.find('.value-hint').text(opt.data('hint') || '');
    });
    $af.on('submit', (e) => { e.preventDefault(); HCMS.submitForm($af, { onSuccess: () => { aModal.hide(); tables.employee.reload(); } }); });

    [['#adhocForm', '#adhocModal', 'adhoc'], ['#loanForm', '#loanModal', 'loan']].forEach(([f, m, t]) => {
        $(f).on('submit', function (e) {
            e.preventDefault();
            HCMS.submitForm($(this), { onSuccess: () => { bootstrap.Modal.getInstance($(m)[0]).hide(); this.reset(); if (loaded[t]) tables[t].reload(); } });
        });
    });
});
</script>
@endpush
