<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo-mark.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    @include('layouts.partials.sidebar')
    <div class="sidebar-backdrop"></div>

    <div class="app-main">
        @include('layouts.partials.topbar')

        <main class="app-content">
            @yield('content')
        </main>

        <footer class="app-footer">
            <span>&copy; {{ date('Y') }} NexaHCMS Enterprise Suite. Laravel {{ app()->version() }}</span>
            <span class="d-flex align-items-center gap-1"><span class="badge-soft dot success border-0 p-0 bg-transparent"></span>Status: Connected ({{ config('database.default') }})</span>
        </footer>
    </div>

    {{-- Modal konfirmasi bersama untuk HCMS.confirm() --}}
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-icon bg-warning-subtle text-warning"><span class="material-symbols-outlined">help</span></div>
                    <h5 class="modal-title confirm-title flex-grow-1">Konfirmasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="confirm-message mb-0"></p>
                    <div class="confirm-input d-none mt-3">
                        <label class="form-label">Alasan <span class="req">*</span></label>
                        <textarea class="form-control" rows="3"></textarea>
                        <div class="invalid-feedback">Alasan wajib diisi.</div>
                    </div>
                </div>
                <div class="modal-footer justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary confirm-ok">Ya, lanjutkan</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/id.js"></script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @if (session('status'))
        <script>$(() => HCMS.toast(@json(session('status'))));</script>
    @endif
    @stack('scripts')
</body>
</html>
