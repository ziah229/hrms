<div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="employeeForm" method="post" enctype="multipart/form-data" novalidate>
            <div class="modal-header">
                <div class="modal-icon"><span class="material-symbols-outlined">person_add</span></div>
                <div class="flex-grow-1">
                    <h5 class="modal-title">Tambah Data Karyawan Baru</h5>
                    <p class="modal-sub">Formulir master personalia & kepatuhan payroll.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="form-section">1. Identitas & Legalitas</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">NIK Karyawan (Auto-Generated)</label>
                        <div class="input-group">
                            <input type="text" name="employee_no" class="form-control" readonly>
                            <span class="input-group-text bg-light"><span class="material-symbols-outlined ms-sm">lock</span></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Lengkap (Sesuai KTP) <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="Contoh: Budi Santoso, S.Kom">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nomor Induk Kependudukan (KTP 16 digit) <span class="req">*</span></label>
                        <input type="text" name="nik_ktp" class="form-control" maxlength="16" inputmode="numeric" placeholder="3271xxxxxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nomor Pokok Wajib Pajak (NPWP 16 digit)</label>
                        <input type="text" name="npwp" class="form-control" maxlength="16" inputmode="numeric" placeholder="15/16 digit format baru">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Jenis Kelamin <span class="req">*</span></label>
                        <select name="gender" class="form-select"><option value="">Pilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tempat Lahir</label>
                        <input type="text" name="birth_place" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="birth_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Agama</label>
                        <select name="religion" class="form-select"><option value="">Pilih</option>
                            @foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $r)<option>{{ $r }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status Pernikahan</label>
                        <select name="marital_status" class="form-select"><option value="">Pilih</option><option>Lajang</option><option>Menikah</option><option>Cerai</option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jumlah Tanggungan</label>
                        <input type="number" name="dependents" class="form-control" min="0" max="10" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status PTKP <span class="req">*</span></label>
                        <select name="ptkp_status" class="form-select">
                            @foreach (array_keys(config('payroll.ter_category')) as $p)<option>{{ $p }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div class="form-section">2. Kontak & Akun Perusahaan</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Email Perusahaan / SSO <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="nama@nexa.co.id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No. WhatsApp / Telepon Aktif <span class="req">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="form-section">3. Penempatan & Status Kerja</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Departemen <span class="req">*</span></label>
                        <select name="department_id" class="form-select"><option value="">Pilih Departemen Unit</option>
                            @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Jabatan / Posisi <span class="req">*</span></label>
                        <select name="position_id" class="form-select"><option value="">Pilih Jabatan</option>
                            @foreach ($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Lokasi Kerja</label>
                        <select name="work_location_id" class="form-select"><option value="">Pilih Lokasi</option>
                            @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Atasan Langsung</label>
                        <select name="manager_id" class="form-select"><option value="">— Tidak ada —</option>
                            @foreach ($managers as $m)<option value="{{ $m->id }}">{{ $m->full_name }} ({{ $m->employee_no }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status Hubungan Kerja <span class="req">*</span></label>
                        <div class="d-flex flex-wrap gap-3 pt-1 radio-group">
                            @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $k => $v)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="employment_status" id="es_{{ $k }}" value="{{ $k }}" @checked($k === 'tetap')>
                                    <label class="form-check-label" for="es_{{ $k }}">{{ $v }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Mulai Bergabung <span class="req">*</span></label>
                        <input type="date" name="join_date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-3 contract-field d-none">
                        <label class="form-label">Akhir Kontrak <span class="req">*</span></label>
                        <input type="date" name="contract_end_date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No. BPJS Kesehatan</label>
                        <input type="text" name="bpjs_kesehatan_no" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No. BPJS Ketenagakerjaan</label>
                        <input type="text" name="bpjs_tk_no" class="form-control">
                    </div>
                </div>

                <div class="form-section">4. Data Payroll & Perbankan</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Bank Payroll Mitra <span class="req">*</span></label>
                        <select name="bank_name" class="form-select">
                            @foreach ($banks as $b)<option>{{ $b }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nomor Rekening Bank <span class="req">*</span></label>
                        <input type="text" name="account_no" class="form-control" inputmode="numeric" placeholder="Masukkan nomor rekening valid">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Atas Nama Rekening</label>
                        <input type="text" name="account_name" class="form-control" placeholder="Default: nama karyawan">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Foto</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="hint"><span class="material-symbols-outlined ms-sm">check_circle</span>Data langsung terdaftar di sistem Payroll</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">check_circle</span><span class="btn-save-label">Simpan Data Karyawan</span></button>
                </div>
            </div>
        </form>
    </div>
</div>
