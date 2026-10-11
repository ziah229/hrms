@extends('layouts.app')

@section('title', 'Data Karyawan')
@section('breadcrumb')
    <li class="breadcrumb-item">Kepegawaian</li>
    <li class="breadcrumb-item active">Data Karyawan</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">Kepegawaian · <span class="accent">Data Master</span></div>
        <h1 class="page-title">Manajemen Data Karyawan</h1>
        <p class="page-desc">Kelola identitas, penempatan, status kerja, dan data payroll karyawan.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @can('employee.create')
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><span class="material-symbols-outlined">upload_file</span>Import Excel</button>
        @endcan
        @can('employee.export')
            <a href="{{ route('employees.export') }}" class="btn btn-outline-secondary" id="btnExport"><span class="material-symbols-outlined">download</span>Export XLSX</a>
        @endcan
        @can('employee.create')
            <button class="btn btn-primary" id="btnCreate"><span class="material-symbols-outlined">person_add</span>Tambah Karyawan</button>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach ([['Total Karyawan', $stats['total'], 'groups', 'slate', 'Termasuk nonaktif'], ['Karyawan Aktif', $stats['active'], 'how_to_reg', 'green', 'Terdaftar di payroll'], ['Kontrak (PKWT)', $stats['kontrak'], 'contract', 'amber', 'Perlu monitoring masa kontrak'], ['Magang', $stats['magang'], 'school', 'slate', 'Program internship']] as [$label, $value, $icon, $color, $foot])
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card">
                <div class="d-flex justify-content-between"><span class="stat-label">{{ $label }}</span><span class="stat-icon {{ $color }}"><span class="material-symbols-outlined">{{ $icon }}</span></span></div>
                <div class="stat-value">{{ $value }}</div>
                <div class="stat-foot">{{ $foot }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <form class="p-3 border-bottom row g-2 align-items-center" id="filterForm">
        <div class="col-lg-4">
            <div class="input-icon"><span class="material-symbols-outlined">search</span>
                <input type="search" name="q" class="form-control" placeholder="Cari nama, NIK karyawan, atau email..." value="{{ request('q') }}">
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <select name="department_id" class="form-select"><option value="">Semua Departemen</option>
                @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <select name="position_id" class="form-select"><option value="">Semua Jabatan</option>
                @foreach ($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <select name="status" class="form-select"><option value="">Status Kerja</option>
                @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <select name="active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif / Resign</option><option value="">Semua</option></select>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-hc table-hover">
            <thead>
                <tr><th>NIK Karyawan</th><th>Nama Lengkap</th><th>Departemen & Jabatan</th><th>Status</th><th>Rekening Payroll</th><th>Keaktifan</th><th class="text-end">Tindakan</th></tr>
            </thead>
            <tbody id="employeeRows"></tbody>
        </table>
    </div>
    <div class="table-footer" id="employeeFooter"></div>
</div>

@canany(['employee.create', 'employee.update'])
    @include('employees.partials.form-modal')
@endcanany

@can('employee.update')
<div class="modal fade" id="resignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="resignForm" method="post">
            <div class="modal-header">
                <div class="modal-icon bg-danger"><span class="material-symbols-outlined">person_off</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Nonaktifkan Karyawan</h5><p class="modal-sub resign-name"></p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Tanggal Keluar <span class="req">*</span></label><input type="date" name="resign_date" class="form-control" value="{{ now()->toDateString() }}"></div>
                <div><label class="form-label">Alasan</label><input type="text" name="reason" class="form-control" placeholder="Resign / habis kontrak / PHK"></div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-danger" type="submit">Nonaktifkan</button>
            </div>
        </form>
    </div>
</div>
@endcan

@can('employee.create')
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="importForm" action="{{ route('employees.import') }}" method="post" enctype="multipart/form-data">
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">upload_file</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Import Karyawan dari Excel</h5><p class="modal-sub">Format .xlsx dengan baris pertama sebagai header.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="file" name="file" class="form-control mb-3" accept=".xlsx,.xls,.csv">
                <div class="fs-xs text-muted-hc">Kolom: <span class="mono">nama, ktp, email, telepon, kode_departemen, kode_jabatan, status_kerja, tanggal_masuk, ptkp, bank, no_rekening</span>.
                    Kode departemen: {{ $departments->pluck('code')->join(', ') }}. Kode jabatan: {{ $positions->pluck('code')->join(', ') }}.</div>
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
    const STATUS = { tetap: ['success', 'TETAP'], kontrak: ['warning', 'PKWT'], magang: ['slate', 'MAGANG'] };
    const can = { update: @json(auth()->user()->hasPermission('employee.update')), del: @json(auth()->user()->hasPermission('employee.delete')) };

    const table = new HCMS.Table({
        url: @json(route('employees.index')),
        tbody: '#employeeRows', footer: '#employeeFooter', filters: '#filterForm', colspan: 7,
        emptyText: 'Tidak ada karyawan yang cocok dengan filter.',
        render: (r) => `
            <tr>
                <td><a href="${@json(url('employees'))}/${r.id}" class="code-link text-decoration-none">${HCMS.esc(r.employee_no)}</a></td>
                <td><div class="d-flex align-items-center gap-2">${HCMS.avatar(r.full_name)}
                    <div><div class="cell-title">${HCMS.esc(r.full_name)}</div><div class="cell-sub">${HCMS.esc(r.email || '-')}</div></div></div></td>
                <td><div>${HCMS.esc(r.department || '-')}</div><div class="cell-sub">${HCMS.esc(r.position || '-')}</div></td>
                <td><span class="badge-soft ${STATUS[r.employment_status][0]}">${STATUS[r.employment_status][1]}</span></td>
                <td>${r.bank ? `<div>${HCMS.esc(r.bank)}</div><div class="cell-sub">a.n ${HCMS.esc(r.bank_owner)}</div>` : '<span class="text-muted-hc">—</span>'}</td>
                <td>${r.is_active ? HCMS.badge('active') : `<span class="badge-soft slate">Resign ${HCMS.esc(r.resign_date || '')}</span>`}</td>
                <td class="text-end text-nowrap">
                    <a class="btn-icon" href="${@json(url('employees'))}/${r.id}" title="Detail"><span class="material-symbols-outlined">visibility</span></a>
                    ${can.update ? `<button class="btn-icon btn-edit" data-id="${r.id}" title="Edit"><span class="material-symbols-outlined">edit_note</span></button>` : ''}
                    ${can.update && r.is_active ? `<button class="btn-icon danger btn-resign" data-id="${r.id}" data-name="${HCMS.esc(r.full_name)}" title="Nonaktifkan"><span class="material-symbols-outlined">person_off</span></button>` : ''}
                    ${can.del ? `<button class="btn-icon danger btn-delete" data-id="${r.id}" title="Hapus"><span class="material-symbols-outlined">delete</span></button>` : ''}
                </td>
            </tr>`
    });
    table.load();

    // Export mengikuti filter aktif.
    $('#btnExport').on('click', function () {
        this.href = @json(route('employees.export')) + '?' + $('#filterForm').serialize();
    });

    // Tambah / edit via modal AJAX.
    const $modal = $('#employeeModal'), $form = $('#employeeForm');
    const modal = $modal.length ? bootstrap.Modal.getOrCreateInstance($modal[0]) : null;

    function toggleContract() {
        const s = $form.find('[name="employment_status"]:checked').val();
        $form.find('.contract-field').toggleClass('d-none', s === 'tetap');
    }
    $form.on('change', '[name="employment_status"]', toggleContract);

    function openCreate() {
        HCMS.resetForm($form);
        $form.attr('action', @json(route('employees.store'))).attr('data-method', 'POST');
        $form.find('[name="employee_no"]').val(@json($nextNo));
        $modal.find('.modal-title').text('Tambah Data Karyawan Baru');
        $modal.find('.btn-save-label').text('Simpan Data Karyawan');
        toggleContract();
        modal.show();
    }
    $('#btnCreate').on('click', openCreate);
    @if (request('create')) if (modal) openCreate(); @endif

    $(document).on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        $.getJSON(`${@json(url('employees'))}/${id}`).done((data) => {
            HCMS.resetForm($form);
            HCMS.fillForm($form, data);
            $form.attr('action', `${@json(url('employees'))}/${id}`).attr('data-method', 'PUT');
            $modal.find('.modal-title').text('Edit Data Karyawan');
            $modal.find('.btn-save-label').text('Simpan Perubahan');
            toggleContract();
            modal.show();
        });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        HCMS.submitForm($form, { onSuccess: () => { modal.hide(); table.reload(); } });
    });

    // Resign
    $(document).on('click', '.btn-resign', function () {
        $('#resignForm').attr('action', `${@json(url('employees'))}/${$(this).data('id')}/resign`);
        $('#resignModal .resign-name').text($(this).data('name'));
        bootstrap.Modal.getOrCreateInstance($('#resignModal')[0]).show();
    });
    $('#resignForm').on('submit', function (e) {
        e.preventDefault();
        HCMS.submitForm($(this), { onSuccess: () => { bootstrap.Modal.getInstance($('#resignModal')[0]).hide(); table.reload(); } });
    });

    $(document).on('click', '.btn-delete', function () {
        HCMS.action(`${@json(url('employees'))}/${$(this).data('id')}`, 'DELETE', {
            confirm: 'Hapus data karyawan ini? Data akan dipindahkan ke arsip (soft delete).', danger: true, okText: 'Hapus',
            onSuccess: () => table.reload()
        });
    });

    // Import
    $('#importForm').on('submit', function (e) {
        e.preventDefault();
        const $f = $(this);
        HCMS.submitForm($f, {
            onSuccess: (res) => {
                $f.find('.import-errors').html((res.errors_detail || []).map((m) => `<li>${HCMS.esc(m)}</li>`).join(''));
                if (!res.errors_detail?.length) bootstrap.Modal.getInstance($('#importModal')[0]).hide();
                table.reload();
            }
        });
    });
});
</script>
@endpush
