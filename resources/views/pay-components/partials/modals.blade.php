@canany(['component.create', 'component.update'])
<div class="modal fade" id="componentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="componentForm" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">sell</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Tambah Komponen Baru</h5><p class="modal-sub">Definisikan tunjangan atau potongan beserta cara hitungnya.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Kode <span class="req">*</span></label><input type="text" name="code" class="form-control text-uppercase mono" placeholder="ALW-XXX"></div>
                    <div class="col-md-8"><label class="form-label">Nama Komponen <span class="req">*</span></label><input type="text" name="name" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Tipe <span class="req">*</span></label>
                        <select name="type" class="form-select"><option value="allowance">Allowance (Tunjangan)</option><option value="deduction">Deduction (Potongan)</option></select></div>
                    <div class="col-md-4"><label class="form-label">Sifat <span class="req">*</span></label>
                        <select name="nature" class="form-select"><option value="fixed">Tetap</option><option value="variable">Variabel</option></select></div>
                    <div class="col-md-4"><label class="form-label">Cara Hitung <span class="req">*</span></label>
                        <select name="calc_method" class="form-select">@foreach ($methods as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Nilai Default <span class="req">*</span></label><input type="number" name="default_value" class="form-control" min="0" step="any" value="0"></div>
                    <div class="col-md-8"><label class="form-label">Rumus (opsional)</label><input type="text" name="formula" class="form-control mono" placeholder="mis. basic * 0.05"></div>
                    <div class="col-md-6">
                        <input type="hidden" name="is_taxable" value="0">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_taxable" value="1" id="c_tax"><label class="form-check-label" for="c_tax">Kena pajak (objek PPh 21)</label></div>
                    </div>
                    <div class="col-md-6">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="c_active" checked><label class="form-check-label" for="c_active">Aktif</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="hint"><span class="material-symbols-outlined ms-sm">history</span>Perubahan tercatat di audit trail</span>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan Komponen</button></div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="assignForm" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">badge</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Komponen per Karyawan</h5><p class="modal-sub">Komponen tetap/berulang dengan periode berlaku.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Karyawan <span class="req">*</span></label>
                    <select name="employee_id" class="form-select"><option value="">Pilih karyawan</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Komponen <span class="req">*</span></label>
                    <select name="pay_component_id" class="form-select"><option value="">Pilih komponen</option>
                        @foreach ($components->whereNotIn('calc_method', ['formula']) as $c)
                            <option value="{{ $c->id }}" data-default="{{ (float) $c->default_value }}" data-hint="{{ $methods[$c->calc_method] }}">{{ $c->code }} — {{ $c->name }}</option>
                        @endforeach
                    </select></div>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Nilai (Rp / %) <span class="req">*</span></label><input type="number" name="value" class="form-control" min="0" step="any"><div class="form-text fs-xs value-hint"></div></div>
                    <div class="col-6"><label class="form-label">Mulai <span class="req">*</span></label><input type="date" name="start_date" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}"></div>
                    <div class="col-6"><label class="form-label">Selesai</label><input type="date" name="end_date" class="form-control"></div>
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

@can('component.create')
<div class="modal fade" id="adhocModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="adhocForm" action="{{ route('components.adhoc.store') }}" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">bolt</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Input Komponen Sekali Bayar</h5><p class="modal-sub">Bonus, insentif, THR, atau potongan khusus untuk satu periode.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Karyawan <span class="req">*</span></label>
                    <select name="employee_id" class="form-select"><option value="">Pilih karyawan</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Komponen <span class="req">*</span></label>
                    <select name="pay_component_id" class="form-select">@foreach ($components->where('nature', 'variable')->whereNotIn('calc_method', ['formula']) as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></div>
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Periode Gaji <span class="req">*</span></label>
                        <select name="payroll_period_id" class="form-select">@foreach ($periods as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label">Jumlah (Rp) <span class="req">*</span></label><input type="number" name="amount" class="form-control" min="1"></div>
                    <div class="col-12"><label class="form-label">Keterangan</label><input type="text" name="notes" class="form-control" placeholder="mis. Bonus kinerja Q3"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="loanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="loanForm" action="{{ route('components.loans.store') }}" method="post" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">credit_card</span></div>
                <div class="flex-grow-1"><h5 class="modal-title">Catat Kasbon / Pinjaman</h5><p class="modal-sub">Cicilan bulanan otomatis menjadi potongan (DED-LOAN).</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Karyawan <span class="req">*</span></label>
                    <select name="employee_id" class="form-select"><option value="">Pilih karyawan</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach</select></div>
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Total Pinjaman (Rp) <span class="req">*</span></label><input type="number" name="total_amount" class="form-control" min="100000" step="50000"></div>
                    <div class="col-6"><label class="form-label">Tenor (bulan) <span class="req">*</span></label><input type="number" name="tenor_months" class="form-control" min="1" max="60" value="10"></div>
                    <div class="col-12"><label class="form-label">Mulai Dipotong <span class="req">*</span></label><input type="date" name="start_date" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Simpan</button>
            </div>
        </form>
    </div>
</div>
@endcan
