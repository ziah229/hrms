/* =========================================================
   NexaHCMS — helper AJAX (jQuery + Bootstrap 5)
   ========================================================= */
window.HCMS = (function ($) {
    'use strict';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    // Error global: sesi habis / tidak berhak / server error.
    $(document).ajaxError(function (e, xhr, settings) {
        if (settings.skipGlobalError) return;
        if (xhr.status === 401 || xhr.status === 419) {
            toast('Sesi berakhir, silakan login ulang.', 'danger');
            setTimeout(() => location.reload(), 1200);
        } else if (xhr.status === 403) {
            toast(xhr.responseJSON?.message || 'Anda tidak memiliki akses.', 'danger');
        } else if (xhr.status >= 500) {
            toast('Terjadi kesalahan server. Silakan coba lagi.', 'danger');
        } else if (xhr.status === 422 && !settings.isFormSubmit) {
            toast(firstError(xhr) || 'Data tidak valid.', 'danger');
        }
    });

    function esc(v) {
        return $('<div>').text(v ?? '').html();
    }

    function rupiah(n, withPrefix = true) {
        const v = Math.round(Number(n || 0));
        const s = Math.abs(v).toLocaleString('id-ID');
        return (v < 0 ? '-' : '') + (withPrefix ? 'Rp ' : '') + s;
    }

    function firstError(xhr) {
        const errors = xhr.responseJSON?.errors;
        if (errors) return Object.values(errors)[0][0];
        return xhr.responseJSON?.message;
    }

    function toast(message, type = 'success') {
        const icon = { success: 'check_circle', danger: 'error', warning: 'warning', info: 'info' }[type] || 'info';
        const color = { success: 'text-primary-hc', danger: 'text-danger', warning: 'text-warning', info: 'text-info' }[type];
        const $t = $(`
            <div class="toast align-items-center bg-white" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex gap-2 align-items-start">
                        <span class="material-symbols-outlined ${color}">${icon}</span><span>${esc(message)}</span>
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`);
        $('#toastContainer').append($t);
        const t = new bootstrap.Toast($t[0], { delay: 3500 });
        $t.on('hidden.bs.toast', () => $t.remove());
        t.show();
    }

    /**
     * Konfirmasi memakai modal Bootstrap bersama (#confirmModal). Mengembalikan Promise<boolean|string>.
     * opts.input = true menampilkan textarea (mis. alasan reopen); resolve berisi teksnya.
     */
    function confirm(message, opts = {}) {
        return new Promise((resolve) => {
            const $m = $('#confirmModal');
            $m.find('.confirm-title').text(opts.title || 'Konfirmasi');
            $m.find('.confirm-message').html(message);
            $m.find('.confirm-input').toggleClass('d-none', !opts.input).find('textarea').val('');
            const $ok = $m.find('.confirm-ok')
                .text(opts.okText || 'Ya, lanjutkan')
                .removeClass('btn-primary btn-danger')
                .addClass(opts.danger ? 'btn-danger' : 'btn-primary');
            const modal = bootstrap.Modal.getOrCreateInstance($m[0]);
            let result = false;
            $ok.off('click').on('click', function () {
                if (opts.input) {
                    const val = $m.find('textarea').val().trim();
                    if (!val) { $m.find('textarea').addClass('is-invalid'); return; }
                    result = val;
                } else {
                    result = true;
                }
                modal.hide();
            });
            $m.find('textarea').removeClass('is-invalid');
            $m.off('hidden.bs.modal').on('hidden.bs.modal', () => resolve(result));
            modal.show();
        });
    }

    /**
     * Ubah semua <select> menjadi Select2 (tema Bootstrap 5).
     * Kecualikan dengan class .no-select2. Aman dipanggil berulang pada konten dinamis.
     */
    function initSelect2(context = document) {
        if (!$.fn.select2) return;
        $(context).find('select').not('.no-select2, .select2-hidden-accessible').each(function () {
            const $s = $(this);
            const $modal = $s.closest('.modal');
            const $empty = $s.find('option[value=""]').first();
            $s.select2({
                theme: 'bootstrap-5',
                language: 'id',
                width: $s.attr('style') ? 'resolve' : '100%',
                dropdownParent: $modal.length ? $modal : $(document.body),
                placeholder: $empty.length ? $empty.text() : undefined,
                allowClear: false,
                minimumResultsForSearch: 6,
                selectionCssClass: $s.hasClass('form-select-sm') ? 'select2--small' : '',
                dropdownCssClass: $s.hasClass('form-select-sm') ? 'select2--small' : ''
            });
        });
    }

    /** Sinkronkan tampilan Select2 setelah nilai diubah lewat kode. */
    function syncSelect2($scope) {
        $scope.find('select.select2-hidden-accessible').trigger('change.select2');
    }

    function clearErrors($form) {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback.ajax').remove();
    }

    function showErrors($form, errors) {
        Object.entries(errors).forEach(([name, messages]) => {
            // Mendukung nama field bertitik (items.0.amount) maupun array (items[]).
            const htmlName = name.replace(/\.(\w+)/g, '[$1]');
            let $f = $form.find(`[name="${name}"], [name="${htmlName}"], [name="${name}[]"]`).first();
            if (!$f.length) return;
            $f.addClass('is-invalid');
            let $target = $f.closest('.input-group, .input-icon').length ? $f.closest('.input-group, .input-icon') : $f;
            if ($f.hasClass('select2-hidden-accessible')) $target = $f.next('.select2-container');
            if ($f.is(':radio, :checkbox')) {
                $f.closest('.radio-group, .form-check').last().after(`<div class="invalid-feedback ajax d-block">${esc(messages[0])}</div>`);
            } else {
                $target.after(`<div class="invalid-feedback ajax d-block">${esc(messages[0])}</div>`);
            }
        });
        $form.find('.is-invalid').first().trigger('focus');
    }

    /**
     * Submit form via AJAX. Method spoofing mengikuti atribut data-method / input _method.
     */
    function submitForm($form, opts = {}) {
        clearErrors($form);
        const $btn = $form.find('[type="submit"]').add(opts.button || $());
        const original = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Menyimpan...');

        const data = new FormData($form[0]);
        const method = ($form.attr('data-method') || $form.find('[name="_method"]').val() || 'POST').toUpperCase();
        if (method !== 'POST') data.set('_method', method);

        return $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            isFormSubmit: true
        }).done(function (res) {
            if (res.message) toast(res.message, 'success');
            opts.onSuccess && opts.onSuccess(res);
        }).fail(function (xhr) {
            if (xhr.status === 422) {
                showErrors($form, xhr.responseJSON.errors || {});
                toast(firstError(xhr) || 'Periksa kembali isian form.', 'danger');
            }
        }).always(function () {
            $btn.prop('disabled', false).html(original);
        });
    }

    /** Kirim aksi sederhana (POST/PUT/DELETE) dengan konfirmasi opsional. */
    async function action(url, method = 'POST', opts = {}) {
        let extra = {};
        if (opts.confirm) {
            const ok = await confirm(opts.confirm, opts);
            if (!ok) return;
            if (opts.input) extra[opts.inputName || 'reason'] = ok;
        }
        return $.ajax({ url, method: 'POST', data: Object.assign({ _method: method }, opts.data || {}, extra) })
            .done(function (res) {
                if (res.message) toast(res.message, 'success');
                opts.onSuccess && opts.onSuccess(res);
            });
    }

    /** Isi form dari objek JSON; key disesuaikan dengan atribut name. */
    function fillForm($form, data) {
        clearErrors($form);
        Object.entries(data || {}).forEach(([key, value]) => {
            let $f = $form.find(`[name="${key}"]`).not('[type="file"]');
            // Pola hidden + checkbox bernama sama: isi checkbox-nya saja.
            if ($f.length > 1 && $f.filter(':checkbox').length) $f = $f.filter(':checkbox');
            if (!$f.length) return;
            if ($f.is(':radio')) {
                $f.prop('checked', false).filter(`[value="${value}"]`).prop('checked', true);
            } else if ($f.is(':checkbox')) {
                $f.prop('checked', !!Number(value) || value === true);
            } else {
                $f.val(value ?? '');
            }
        });
        syncSelect2($form);
    }

    function resetForm($form) {
        $form[0].reset();
        clearErrors($form);
        $form.find('input[type="hidden"][data-reset]').val('');
        syncSelect2($form);
    }

    /**
     * Tabel AJAX berbasis JSON paginator Laravel.
     * new HCMS.Table({ url, tbody, footer, filters, columns, render(row) })
     */
    class Table {
        constructor(opts) {
            this.opts = Object.assign({ perPage: 10, colspan: 8, emptyText: 'Tidak ada data.' }, opts);
            this.$tbody = $(opts.tbody);
            this.$footer = $(opts.footer);
            this.$filters = opts.filters ? $(opts.filters) : $();
            this.page = 1;
            this.xhr = null;

            let timer;
            this.$filters.on('input', 'input[type="search"], input[type="text"]', () => {
                clearTimeout(timer);
                timer = setTimeout(() => this.load(1), 350);
            });
            this.$filters.on('change', 'select, input[type="date"], input[type="month"]', () => this.load(1));
            this.$filters.on('submit', (e) => { e.preventDefault(); this.load(1); });
            this.$footer.on('click', '.page-link[data-page]', (e) => {
                e.preventDefault();
                this.load(Number($(e.currentTarget).data('page')));
            });
            this.$footer.on('change', '.per-page', (e) => { this.opts.perPage = $(e.target).val(); this.load(1); });
        }

        params() {
            const p = { page: this.page, per_page: this.opts.perPage };
            this.$filters.serializeArray().forEach(({ name, value }) => { if (value !== '') p[name] = value; });
            return Object.assign(p, typeof this.opts.extraParams === 'function' ? this.opts.extraParams() : {});
        }

        load(page) {
            if (page) this.page = page;
            this.xhr && this.xhr.abort();
            const wrap = (html, cls = '') => this.opts.list ? `<div class="empty-state ${cls}">${html}</div>` : `<tr class="${cls}"><td colspan="${this.opts.colspan}">${html}</td></tr>`;
            this.$tbody.html(wrap('<span class="spinner-border spinner-border-sm text-success me-2"></span>Memuat data...', 'table-loading'));
            this.xhr = $.getJSON(this.opts.url, this.params()).done((res) => {
                this.last = res;
                const rows = res.data || [];
                this.$tbody.html(rows.length
                    ? rows.map((r) => this.opts.render(r)).join('')
                    : wrap(this.opts.list ? `<span class="material-symbols-outlined">inbox</span>${this.opts.emptyText}` : `<div class="empty-state"><span class="material-symbols-outlined">inbox</span>${this.opts.emptyText}</div>`));
                this.renderFooter(res);
                this.opts.onLoad && this.opts.onLoad(res);
            });
            return this.xhr;
        }

        reload() { return this.load(this.page); }

        renderFooter(res) {
            if (!this.$footer.length) return;
            const cur = res.current_page, last = res.last_page;
            const pages = [];
            for (let i = 1; i <= last; i++) {
                if (i === 1 || i === last || Math.abs(i - cur) <= 1) pages.push(i);
                else if (pages[pages.length - 1] !== '...') pages.push('...');
            }
            const li = (label, page, disabled, active) =>
                `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                    <a class="page-link" href="#" ${page ? `data-page="${page}"` : ''}>${label}</a></li>`;
            const html = [li('Sebelumnya', cur - 1, cur <= 1)]
                .concat(pages.map((p) => p === '...' ? li('…', null, true) : li(p, p, false, p === cur)))
                .concat([li('Berikutnya', cur + 1, cur >= last)]).join('');

            this.$footer.html(`
                <div class="d-flex align-items-center gap-2">
                    <span>Menampilkan</span>
                    <select class="form-select form-select-sm per-page" style="width:80px">
                        ${[10, 25, 50, 100].map((n) => `<option ${Number(this.opts.perPage) === n ? 'selected' : ''}>${n}</option>`).join('')}
                    </select>
                    <span><b>${res.from || 0}</b>–<b>${res.to || 0}</b> dari <b>${res.total}</b> data</span>
                </div>
                <ul class="pagination">${html}</ul>`);
            initSelect2(this.$footer);
        }
    }

    const STATUS_BADGE = {
        // absensi
        hadir: ['success', 'Hadir Tepat Waktu'], terlambat: ['danger', 'Terlambat'], izin: ['slate', 'Izin'],
        sakit: ['slate', 'Sakit (SKD)'], cuti: ['info', 'Cuti'], alpha: ['danger', 'Alpha'], libur: ['slate', 'Libur'], dinas: ['dark', 'Dinas Luar'],
        // pengajuan
        pending: ['warning', 'Menunggu Approval'], approved_manager: ['info', 'Disetujui Atasan'], approved: ['success', 'Disetujui'], rejected: ['danger', 'Ditolak'],
        // payroll
        draft: ['slate', 'Draft'], processed: ['info', 'Diproses'], verified: ['success', 'Verified'], review: ['warning', 'Perlu Review'], final: ['dark', 'Final & Locked'],
        // umum
        active: ['success', 'Aktif'], inactive: ['slate', 'Nonaktif'], paid: ['success', 'Lunas']
    };

    function badge(status, label) {
        const [cls, text] = STATUS_BADGE[status] || ['slate', status];
        return `<span class="badge-soft dot ${cls}">${esc(label || text)}</span>`;
    }

    function avatar(name, cls = '') {
        const parts = String(name || '?').trim().split(/\s+/);
        const ini = (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
        return `<span class="avatar ${cls}">${esc(ini)}</span>`;
    }

    function initSidebar() {
        $('#sidebarToggle').on('click', function () {
            if (window.innerWidth < 1024) $('body').toggleClass('sidebar-open');
            else $('body').toggleClass('sidebar-collapsed');
        });
        $('.sidebar-backdrop').on('click', () => $('body').removeClass('sidebar-open'));
    }

    $(initSidebar);
    $(() => initSelect2());

    return { initSelect2, syncSelect2, esc, rupiah, toast, confirm, submitForm, action, fillForm, resetForm, clearErrors, Table, badge, avatar, firstError };
})(jQuery);
