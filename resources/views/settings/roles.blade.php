@extends('layouts.app')

@section('title', 'Role & Permission')
@section('breadcrumb')
    <li class="breadcrumb-item">Pengaturan</li>
    <li class="breadcrumb-item active">Role & Permission</li>
@endsection

@php $scopes = ['all' => 'Seluruh perusahaan', 'department' => 'Departemen sendiri', 'subordinate' => 'Bawahan', 'self' => 'Diri sendiri']; @endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="page-eyebrow">Pengaturan · <span class="accent">RBAC</span></div>
        <h1 class="page-title">Role & Permission</h1>
        <p class="page-desc">Permission berformat <span class="mono">modul.aksi</span>. Role bawaan tidak dapat dihapus; Super Admin otomatis memiliki semua permission.</p>
    </div>
    <button class="btn btn-primary btn-role" data-role=""><span class="material-symbols-outlined">add_moderator</span>Tambah Role</button>
</div>

<div class="row g-3 mb-3">
    @foreach ($roles as $role)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100"><div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div><h5 class="card-title-hc">{{ $role->name }}</h5><p class="card-sub">{{ $role->description }}</p></div>
                    @if ($role->is_system)<span class="badge-soft slate"><span class="material-symbols-outlined ms-sm">lock</span>Sistem</span>@endif
                </div>
                <div class="d-flex flex-wrap gap-2 fs-xs mb-3">
                    <span class="badge-soft info">Scope: {{ $scopes[$role->data_scope] }}</span>
                    <span class="badge-soft success">{{ $role->isSuperAdmin() ? 'Semua' : $role->permissions->count() }} permission</span>
                    <span class="badge-soft slate">{{ $role->users_count }} user</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary btn-role" data-role="{{ json_encode(['id' => $role->id, 'name' => $role->name, 'description' => $role->description, 'data_scope' => $role->data_scope, 'is_system' => $role->is_system, 'super' => $role->isSuperAdmin(), 'permissions' => $role->permissions->pluck('id')]) }}"><span class="material-symbols-outlined ms-sm">tune</span>Atur Permission</button>
                    @unless ($role->is_system)<button class="btn btn-sm btn-outline-danger btn-delete" data-id="{{ $role->id }}">Hapus</button>@endunless
                </div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <form class="modal-content" id="roleForm" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">admin_panel_settings</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Role</h5><p class="modal-sub">Centang permission per modul.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><label class="form-label">Nama Role <span class="req">*</span></label><input type="text" name="name" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Data Scope <span class="req">*</span></label><select name="data_scope" class="form-select">@foreach ($scopes as $k => $v)<option value="{{ $k }}">{{ $k }} — {{ $v }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Deskripsi</label><input type="text" name="description" class="form-control"></div>
                </div>
                <div class="alert alert-success py-2 fs-sm super-note d-none">Super Admin otomatis memiliki seluruh permission.</div>
                <div class="row g-3 perm-grid">
                    @foreach ($permissions as $module => $perms)
                        <div class="col-md-6 col-xl-4">
                            <div class="slip-box h-100">
                                <div class="slip-box-head d-flex justify-content-between"><span class="mono">{{ $module }}</span>
                                    <a href="#" class="fs-xs toggle-module text-decoration-none" data-module="{{ $module }}">pilih semua</a></div>
                                <div class="p-2 d-flex flex-wrap gap-2">
                                    @foreach ($perms as $p)
                                        <div class="form-check me-2">
                                            <input class="form-check-input perm" type="checkbox" name="permissions[]" value="{{ $p->id }}" id="p{{ $p->id }}" data-module="{{ $module }}">
                                            <label class="form-check-label fs-sm" for="p{{ $p->id }}">{{ $p->action }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <span class="hint"><span class="material-symbols-outlined ms-sm">history</span>Perubahan role/permission dicatat di audit log</span>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan</button></div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const base = @json(route('settings.roles.index'));
    const $form = $('#roleForm'), modal = bootstrap.Modal.getOrCreateInstance($('#roleModal')[0]);

    $('.btn-role').on('click', function () {
        const r = $(this).data('role') || null;
        HCMS.resetForm($form);
        $form.find('.perm').prop('checked', false).prop('disabled', false);
        $form.find('[name="name"]').prop('readonly', !!(r && r.is_system));
        $('.super-note').toggleClass('d-none', !(r && r.super));
        if (r) {
            HCMS.fillForm($form, r);
            (r.permissions || []).forEach((id) => $(`#p${id}`).prop('checked', true));
            if (r.super) $form.find('.perm').prop('checked', true).prop('disabled', true);
            $form.attr('action', `${base}/${r.id}`).attr('data-method', 'PUT');
            $('#roleModal .modal-title').text('Role: ' + r.name);
        } else {
            $form.attr('action', base).attr('data-method', 'POST');
            $('#roleModal .modal-title').text('Tambah Role');
        }
        modal.show();
    });
    $('.toggle-module').on('click', function (e) {
        e.preventDefault();
        const $boxes = $(`.perm[data-module="${$(this).data('module')}"]:not(:disabled)`);
        $boxes.prop('checked', $boxes.filter(':checked').length !== $boxes.length);
    });
    $form.on('submit', (e) => { e.preventDefault(); HCMS.submitForm($form, { onSuccess: () => setTimeout(() => location.reload(), 500) }); });
    $('.btn-delete').on('click', function () {
        HCMS.action(`${base}/${$(this).data('id')}`, 'DELETE', { confirm: 'Hapus role ini?', danger: true, okText: 'Hapus', onSuccess: () => location.reload() });
    });
});
</script>
@endpush
