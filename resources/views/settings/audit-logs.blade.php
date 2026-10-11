@extends('layouts.app')

@section('title', 'Audit Log')
@section('breadcrumb')
    <li class="breadcrumb-item">Pengaturan</li>
    <li class="breadcrumb-item active">Audit Log</li>
@endsection

@section('content')
<div class="mb-4">
    <div class="page-eyebrow">Pengaturan · <span class="accent">Audit Trail</span></div>
    <h1 class="page-title">Audit Log</h1>
    <p class="page-desc">Jejak perubahan data sensitif, perubahan role/permission, dan percobaan akses yang ditolak.</p>
</div>

<div class="card">
    <form class="p-3 border-bottom row g-2" id="filterForm">
        <div class="col-md-4"><div class="input-icon"><span class="material-symbols-outlined">search</span><input type="search" name="q" class="form-control" placeholder="Cari aksi (mis. payroll, employee.update)"></div></div>
        <div class="col-md-3"><select name="entity" class="form-select"><option value="">Semua Entitas</option>@foreach ($entities as $e)<option @selected(request('entity') === $e)>{{ $e }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="date" name="date" class="form-control"></div>
        <div class="col-md-3 d-flex align-items-center"><input type="hidden" name="denied" value="0"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="denied" value="1" id="denied"><label class="form-check-label fs-sm" for="denied">Hanya akses ditolak</label></div></div>
    </form>
    <div class="table-responsive"><table class="table table-hc table-hover">
        <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Entitas</th><th>Perubahan</th><th>IP</th></tr></thead>
        <tbody id="rows"></tbody>
    </table></div>
    <div class="table-footer" id="footer"></div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const diff = (o, n) => {
        if (!o && !n) return '<span class="text-muted-hc">—</span>';
        const keys = [...new Set([...Object.keys(o || {}), ...Object.keys(n || {})])].slice(0, 6);
        return keys.map((k) => {
            const fmt = (v) => HCMS.esc(typeof v === 'object' && v !== null ? JSON.stringify(v) : v);
            return `<div class="fs-xs"><span class="mono text-muted-hc">${HCMS.esc(k)}</span>: ${o && k in o ? `<s class="text-danger">${fmt(o[k])}</s> → ` : ''}<span class="text-primary-hc">${fmt(n?.[k] ?? '')}</span></div>`;
        }).join('');
    };
    const table = new HCMS.Table({
        url: @json(route('settings.audit-logs')), tbody: '#rows', footer: '#footer', filters: '#filterForm', colspan: 6, perPage: 25,
        render: (r) => `<tr>
            <td class="fs-sm text-nowrap">${r.created_at}</td>
            <td>${HCMS.esc(r.user)}</td>
            <td><span class="badge-soft ${r.action === 'access.denied' ? 'danger' : 'slate'} mono">${HCMS.esc(r.action)}</span></td>
            <td class="fs-sm">${HCMS.esc(r.entity)}</td>
            <td style="max-width:420px">${diff(r.old_values, r.new_values)}</td>
            <td class="fs-xs mono">${HCMS.esc(r.ip_address || '')}</td></tr>`
    });
    table.load();
});
</script>
@endpush
