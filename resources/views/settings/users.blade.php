@extends('layouts.app')

@section('title', 'User')
@section('breadcrumb')
    <li class="breadcrumb-item">Pengaturan</li>
    <li class="breadcrumb-item active">User</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">Pengaturan · <span class="accent">RBAC</span></div>
        <h1 class="page-title">Manajemen User</h1>
        <p class="page-desc">Satu user memiliki satu role. Hubungkan user dengan data karyawan untuk self service & data scope.</p>
    </div>
    <button class="btn btn-primary" id="btnCreate"><span class="material-symbols-outlined">person_add</span>Tambah User</button>
</div>

<div class="card">
    <form class="p-3 border-bottom row g-2" id="filterForm">
        <div class="col-md-5"><div class="input-icon"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari nama / email..."></div></div>
        <div class="col-md-3"><select name="role_id" class="form-select"><option value="">Semua Role</option>@foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
    </form>
    <div class="table-responsive"><table class="table table-hc table-hover">
        <thead><tr><th>User</th><th>Role</th><th>Karyawan Terkait</th><th>Login Terakhir</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody id="rows"></tbody>
    </table></div>
    <div class="table-footer" id="footer"></div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="userForm" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">manage_accounts</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Tambah User</h5><p class="modal-sub">Akun login aplikasi.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label">Nama <span class="req">*</span></label><input type="text" name="name" class="form-control"></div>
                <div class="col-12"><label class="form-label">Email <span class="req">*</span></label><input type="email" name="email" class="form-control"></div>
                <div class="col-6"><label class="form-label">Role <span class="req">*</span></label><select name="role_id" class="form-select">@foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Password <span class="req pw-req">*</span></label><input type="password" name="password" class="form-control" autocomplete="new-password"><div class="form-text fs-xs pw-hint d-none">Kosongkan bila tidak diubah.</div></div>
                <div class="col-12"><label class="form-label">Karyawan Terkait</label><select name="employee_id" class="form-select"><option value="">— Tidak dihubungkan —</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach</select></div>
                <div class="col-12"><input type="hidden" name="is_active" value="0"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="u_active" checked><label class="form-check-label" for="u_active">Akun aktif</label></div></div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const base = @json(route('settings.users.index'));
    const me = {{ auth()->id() }};
    const table = new HCMS.Table({
        url: base, tbody: '#rows', footer: '#footer', filters: '#filterForm', colspan: 6,
        render: (r) => `<tr>
            <td><div class="d-flex align-items-center gap-2">${HCMS.avatar(r.name)}<div><div class="cell-title">${HCMS.esc(r.name)}</div><div class="cell-sub">${HCMS.esc(r.email)}</div></div></div></td>
            <td><span class="badge-soft success">${HCMS.esc(r.role || '-')}</span></td>
            <td class="fs-sm">${HCMS.esc(r.employee || '—')}</td>
            <td class="fs-sm">${r.last_login_at || '<span class="text-muted-hc">Belum pernah</span>'}</td>
            <td>${r.is_active ? HCMS.badge('active') : HCMS.badge('inactive')}</td>
            <td class="text-end text-nowrap">
                <button class="btn-icon btn-edit" data-row='${JSON.stringify(r).replace(/'/g, '&#39;')}'><span class="material-symbols-outlined">edit</span></button>
                ${r.id !== me ? `<button class="btn-icon danger btn-delete" data-id="${r.id}"><span class="material-symbols-outlined">delete</span></button>` : ''}
            </td></tr>`
    });
    table.load();

    const $form = $('#userForm'), modal = bootstrap.Modal.getOrCreateInstance($('#userModal')[0]);
    $('#btnCreate').on('click', () => {
        HCMS.resetForm($form); $form.attr('action', base).attr('data-method', 'POST');
        $('.pw-req').removeClass('d-none'); $('.pw-hint').addClass('d-none');
        $('#userModal .modal-title').text('Tambah User'); modal.show();
    });
    $(document).on('click', '.btn-edit', function () {
        const r = $(this).data('row');
        HCMS.resetForm($form); HCMS.fillForm($form, r);
        $form.attr('action', `${base}/${r.id}`).attr('data-method', 'PUT');
        $('.pw-req').addClass('d-none'); $('.pw-hint').removeClass('d-none');
        $('#userModal .modal-title').text('Edit User'); modal.show();
    });
    $form.on('submit', (e) => { e.preventDefault(); HCMS.submitForm($form, { onSuccess: () => { modal.hide(); table.reload(); } }); });
    $(document).on('click', '.btn-delete', function () {
        HCMS.action(`${base}/${$(this).data('id')}`, 'DELETE', { confirm: 'Hapus user ini?', danger: true, okText: 'Hapus', onSuccess: () => table.reload() });
    });
});
</script>
@endpush
