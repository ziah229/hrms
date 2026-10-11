@extends('layouts.app')

@section('title', $def['title'])
@section('breadcrumb')
    <li class="breadcrumb-item">{{ $def['section'] }}</li>
    <li class="breadcrumb-item active">{{ $def['title'] }}</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">{{ $def['section'] }} · <span class="accent">Data Master</span></div>
        <h1 class="page-title">{{ $def['title'] }}</h1>
        <p class="page-desc">Tambah, ubah, dan hapus data referensi. Seluruh perubahan tercatat di audit log.</p>
    </div>
    <button class="btn btn-primary" id="btnCreate"><span class="material-symbols-outlined">add_circle</span>Tambah Data</button>
</div>

<div class="card">
    <form class="p-3 border-bottom" id="filterForm">
        <div class="input-icon" style="max-width:360px"><span class="material-symbols-outlined">search</span>
            <input type="search" name="q" class="form-control" placeholder="Cari...">
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-hc table-hover">
            <thead><tr>
                @foreach ($def['fields'] as $f)<th>{{ $f['label'] }}</th>@endforeach
                @isset($def['count'])<th>Dipakai</th>@endisset
                <th class="text-end">Aksi</th>
            </tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
    <div class="table-footer" id="footer"></div>
</div>

<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="masterForm" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">{{ $def['icon'] }}</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Tambah {{ $def['title'] }}</h5><p class="modal-sub">Lengkapi data berikut.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @foreach ($def['fields'] as $name => $f)
                    <div class="mb-3">
                        @if ($f['type'] === 'switch')
                            <input type="hidden" name="{{ $name }}" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="f_{{ $name }}" checked>
                                <label class="form-check-label" for="f_{{ $name }}">{{ $f['label'] }}</label>
                            </div>
                        @else
                            <label class="form-label" for="f_{{ $name }}">{{ $f['label'] }} @if (in_array('required', $f['rules']))<span class="req">*</span>@endif</label>
                            @if ($f['type'] === 'textarea')
                                <textarea name="{{ $name }}" id="f_{{ $name }}" class="form-control" rows="2"></textarea>
                            @else
                                <input type="{{ $f['type'] }}" name="{{ $name }}" id="f_{{ $name }}" class="form-control">
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">save</span>Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const base = @json(route('master.index', $type));
    const fields = @json($def['fields']);
    const hasCount = @json(isset($def['count']));
    const fmt = (f, v) => f.type === 'switch' ? (Number(v) ? HCMS.badge('active', 'Ya') : HCMS.badge('inactive', 'Tidak'))
        : f.type === 'date' ? new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) : HCMS.esc(v ?? '-');

    const table = new HCMS.Table({
        url: base, tbody: '#rows', footer: '#footer', filters: '#filterForm', colspan: Object.keys(fields).length + 2,
        render: (r) => `<tr>
            ${Object.entries(fields).map(([k, f], i) => `<td class="${i === 0 ? 'fw-600' : ''}">${fmt(f, r[k])}</td>`).join('')}
            ${hasCount ? `<td><span class="count-pill">${r.usage}</span></td>` : ''}
            <td class="text-end text-nowrap">
                <button class="btn-icon btn-edit" data-row='${JSON.stringify(r).replace(/'/g, '&#39;')}' title="Edit"><span class="material-symbols-outlined">edit</span></button>
                <button class="btn-icon danger btn-delete" data-id="${r.id}" title="Hapus"><span class="material-symbols-outlined">delete</span></button>
            </td></tr>`
    });
    table.load();

    const $form = $('#masterForm'), modal = bootstrap.Modal.getOrCreateInstance($('#formModal')[0]);
    $('#btnCreate').on('click', () => {
        HCMS.resetForm($form);
        $form.attr('action', base).attr('data-method', 'POST');
        $('#formModal .modal-title').text('Tambah ' + @json($def['title']));
        modal.show();
    });
    $(document).on('click', '.btn-edit', function () {
        const row = $(this).data('row');
        HCMS.resetForm($form);
        HCMS.fillForm($form, row);
        $form.attr('action', `${base}/${row.id}`).attr('data-method', 'PUT');
        $('#formModal .modal-title').text('Edit ' + @json($def['title']));
        modal.show();
    });
    $form.on('submit', (e) => {
        e.preventDefault();
        HCMS.submitForm($form, { onSuccess: () => { modal.hide(); table.reload(); } });
    });
    $(document).on('click', '.btn-delete', function () {
        HCMS.action(`${base}/${$(this).data('id')}`, 'DELETE', { confirm: 'Hapus data ini?', danger: true, okText: 'Hapus', onSuccess: () => table.reload() });
    });
});
</script>
@endpush
