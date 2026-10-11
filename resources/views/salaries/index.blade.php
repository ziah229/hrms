@extends('layouts.app')

@section('title', 'Data Gaji')
@section('breadcrumb')
    <li class="breadcrumb-item">Kompensasi & Benefit</li>
    <li class="breadcrumb-item active">Data Gaji</li>
@endsection

@php $history = request('view') === 'history'; @endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">Kompensasi & Benefit · <span class="accent">{{ $history ? 'Riwayat Perubahan' : 'Gaji Pokok' }}</span></div>
        <h1 class="page-title">{{ $history ? 'Riwayat Perubahan Gaji' : 'Daftar Gaji Pokok Karyawan' }}</h1>
        <p class="page-desc">Gaji pokok dengan tanggal efektif. Setiap perubahan membuat baris baru (riwayat tidak ditimpa).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('salaries.index', $history ? [] : ['view' => 'history']) }}" class="btn btn-outline-secondary"><span class="material-symbols-outlined">{{ $history ? 'list' : 'history' }}</span>{{ $history ? 'Gaji Pokok' : 'Riwayat Perubahan' }}</a>
        @can('salary.export')<a href="{{ route('salaries.export') }}" class="btn btn-outline-secondary"><span class="material-symbols-outlined">download</span>Export XLSX</a>@endcan
        @canany(['salary.create', 'salary.update'])<button class="btn btn-primary btn-change" data-id=""><span class="material-symbols-outlined">trending_up</span>Perubahan Gaji</button>@endcanany
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Total Gaji Pokok Aktif</span><span class="stat-icon green"><span class="material-symbols-outlined">payments</span></span></div><div class="stat-value" style="font-size:1.5rem">@rupiah($totalBasic)</div><div class="stat-foot">Per bulan, karyawan aktif</div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Rata-rata Gaji Pokok</span><span class="stat-icon slate"><span class="material-symbols-outlined">functions</span></span></div><div class="stat-value" style="font-size:1.5rem">@rupiah($avgBasic)</div><div class="stat-foot">Seluruh level jabatan</div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="d-flex justify-content-between"><span class="stat-label">Perubahan Tahun Ini</span><span class="stat-icon amber"><span class="material-symbols-outlined">trending_up</span></span></div><div class="stat-value">{{ $changesThisYear }} <small>perubahan</small></div><div class="stat-foot">Kenaikan / promosi / penyesuaian</div></div></div>
</div>

<div class="card">
    <form class="p-3 border-bottom row g-2" id="filterForm">
        <input type="hidden" name="view" value="{{ request('view') }}">
        <div class="col-md-5"><div class="input-icon"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari nama / NIK karyawan..."></div></div>
        <div class="col-md-3"><select name="department_id" class="form-select"><option value="">Semua Departemen</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
    </form>
    <div class="table-responsive">
        <table class="table table-hc table-hover">
            <thead>
            @if ($history)
                <tr><th>Karyawan</th><th>Departemen</th><th class="text-end">Gaji Pokok</th><th>Skema</th><th>Berlaku</th><th>Alasan</th></tr>
            @else
                <tr><th>NIK</th><th>Karyawan</th><th>Departemen</th><th>PTKP</th><th class="text-end">Gaji Pokok</th><th>Skema</th><th>Efektif Sejak</th><th class="text-end">Aksi</th></tr>
            @endif
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
    <div class="table-footer" id="footer"></div>
</div>

@canany(['salary.create', 'salary.update'])
<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="salaryForm" action="{{ route('salaries.store') }}" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">trending_up</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Perubahan Gaji Pokok</h5><p class="modal-sub">Kenaikan, promosi, atau penyesuaian dengan tanggal efektif.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Karyawan <span class="req">*</span></label>
                    <select name="employee_id" class="form-select"><option value="">Pilih karyawan</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach</select></div>
                <div class="row g-3">
                    <div class="col-7"><label class="form-label">Gaji Pokok Baru (Rp) <span class="req">*</span></label><input type="number" name="basic_salary" class="form-control" min="0" step="1000"></div>
                    <div class="col-5"><label class="form-label">Skema <span class="req">*</span></label><select name="salary_type" class="form-select">@foreach ($types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label">Tanggal Efektif <span class="req">*</span></label><input type="date" name="effective_date" class="form-control" value="{{ now()->addMonth()->startOfMonth()->toDateString() }}"></div>
                    <div class="col-6"><label class="form-label">Alasan <span class="req">*</span></label><select name="reason" class="form-select"><option>Kenaikan berkala</option><option>Promosi jabatan</option><option>Penyesuaian UMR</option><option>Hasil evaluasi kinerja</option><option>Mutasi</option></select></div>
                </div>
                <div class="mt-3 history-box d-none"><div class="page-eyebrow mb-2">Riwayat Gaji</div><ul class="list-group list-group-flush fs-sm history-list"></ul></div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endcanany
@endsection

@push('scripts')
<script>
$(function () {
    const history = @json($history);
    const canChange = @json(auth()->user()->hasPermission('salary.create', 'salary.update'));
    const person = (r) => `<div class="d-flex align-items-center gap-2">${HCMS.avatar(r.full_name)}<div><div class="cell-title">${HCMS.esc(r.full_name)}</div><div class="cell-sub">${HCMS.esc(r.position || r.employee_no)}</div></div></div>`;

    const table = new HCMS.Table({
        url: @json(route('salaries.index')), tbody: '#rows', footer: '#footer', filters: '#filterForm', colspan: history ? 6 : 8,
        render: (r) => history ? `<tr>
                <td>${person(r)}</td><td>${HCMS.esc(r.department || '-')}</td>
                <td class="text-num fw-600">${HCMS.rupiah(r.basic_salary)}</td><td>${r.salary_type}</td>
                <td>${r.effective_date}${r.end_date ? ` – ${r.end_date}` : ' – <span class="badge-soft success">Berlaku</span>'}</td>
                <td class="fs-sm">${HCMS.esc(r.reason || '-')}</td></tr>`
            : `<tr>
                <td class="code-link">${HCMS.esc(r.employee_no)}</td><td>${person(r)}</td><td>${HCMS.esc(r.department || '-')}</td>
                <td><span class="badge-soft slate">${HCMS.esc(r.ptkp_status || '-')}</span></td>
                <td class="text-num fw-600">${HCMS.rupiah(r.basic_salary)}${r.change !== null ? `<div class="fs-xs ${r.change >= 0 ? 'text-primary-hc' : 'text-danger'}">${r.change >= 0 ? '▲' : '▼'} ${Math.abs(r.change)}%</div>` : ''}</td>
                <td>${r.salary_type}</td><td>${r.effective_date || '-'}</td>
                <td class="text-end text-nowrap">
                    <button class="btn-icon btn-history" data-id="${r.employee_id}" title="Riwayat"><span class="material-symbols-outlined">history</span></button>
                    ${canChange ? `<button class="btn-icon btn-change" data-id="${r.employee_id}" title="Ubah gaji"><span class="material-symbols-outlined">edit</span></button>` : ''}
                </td></tr>`
    });
    table.load();

    const $form = $('#salaryForm');
    const modal = $form.length ? bootstrap.Modal.getOrCreateInstance($('#salaryModal')[0]) : null;
    function loadHistory(id) {
        if (!id) return $form.find('.history-box').addClass('d-none');
        $.getJSON(`${@json(url('salaries'))}/${id}/history`).done((res) => {
            $form.find('.history-list').html(res.rows.map((h) => `<li class="list-group-item px-0 d-flex justify-content-between">
                <span>${h.effective_date}${h.end_date ? ' – ' + h.end_date : ''}<div class="cell-sub">${HCMS.esc(h.reason || '')}</div></span><b>${HCMS.rupiah(h.basic_salary)}</b></li>`).join(''));
            $form.find('.history-box').removeClass('d-none');
        });
    }
    $(document).on('click', '.btn-change', function () {
        HCMS.resetForm($form);
        const id = $(this).data('id');
        $form.find('[name="employee_id"]').val(id || '').trigger('change.select2');
        loadHistory(id);
        modal.show();
    });
    $form.on('change', '[name="employee_id"]', function () { loadHistory(this.value); });
    $form.on('submit', (e) => { e.preventDefault(); HCMS.submitForm($form, { onSuccess: () => { modal.hide(); table.reload(); } }); });

    $(document).on('click', '.btn-history', function () {
        if (modal) { $(this).siblings('.btn-change').trigger('click'); return; }
        $.getJSON(`${@json(url('salaries'))}/${$(this).data('id')}/history`).done((res) => {
            HCMS.confirm(res.rows.map((h) => `${h.effective_date}: <b>${HCMS.rupiah(h.basic_salary)}</b> — ${HCMS.esc(h.reason || '')}`).join('<br>'), { title: 'Riwayat Gaji ' + res.employee.full_name, okText: 'Tutup' });
        });
    });
});
</script>
@endpush
