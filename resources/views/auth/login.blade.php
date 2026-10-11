<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo-mark.svg') }}" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <aside class="auth-aside">
        <div class="d-flex align-items-center gap-2">
            <img src="{{ asset('img/logo-mark.svg') }}" width="40" height="40" alt="">
            <div>
                <div class="text-white fw-bold fs-5 lh-1">Nexa<span style="color:#10b981">HCMS</span></div>
                <div style="color:#10b981;font-size:.65rem;letter-spacing:.12em;font-weight:600">ENTERPRISE SUITE</div>
            </div>
        </div>
        <div>
            <h2 class="mb-3">Human Capital Management System</h2>
            <p class="mb-4">Kelola data karyawan, absensi, penggajian, register gaji, payslip, dan pelaporan dalam satu platform terintegrasi.</p>
            <div class="d-flex flex-column gap-2 fs-sm">
                <span class="d-flex align-items-center gap-2"><span class="material-symbols-outlined" style="color:#10b981">verified_user</span>Role-Based Access Control dengan data scope</span>
                <span class="d-flex align-items-center gap-2"><span class="material-symbols-outlined" style="color:#10b981">receipt_long</span>Register gaji dengan approval & lock periode</span>
                <span class="d-flex align-items-center gap-2"><span class="material-symbols-outlined" style="color:#10b981">history</span>Audit trail untuk setiap perubahan data sensitif</span>
            </div>
        </div>
        <div class="fs-xs">&copy; {{ date('Y') }} NexaHCMS Enterprise Suite</div>
    </aside>

    <div class="auth-form">
        <div style="width:100%;max-width:380px">
            <h1 class="page-title mb-1">Masuk ke akun Anda</h1>
            <p class="page-desc mb-4">Gunakan email perusahaan dan password Anda.</p>

            <form method="post" action="{{ url('/login') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <div class="input-icon">
                        <span class="material-symbols-outlined">mail</span>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="nama@nexa.co.id" required autofocus>
                    </div>
                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon">
                        <span class="material-symbols-outlined">lock</span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label fs-sm" for="remember">Ingat saya</label>
                </div>
                <button class="btn btn-primary w-100 py-2"><span class="material-symbols-outlined">login</span>Masuk</button>
            </form>

            @if (app()->isLocal())
                <div class="card mt-4">
                    <div class="card-body p-3">
                        <div class="page-eyebrow mb-2">Akun demo (password: <span class="mono">password</span>)</div>
                        @foreach (['superadmin' => 'Super Admin', 'hr' => 'HR Admin', 'payroll' => 'Payroll Officer', 'manager' => 'Manager', 'karyawan' => 'Karyawan'] as $u => $r)
                            <button type="button" class="btn btn-sm btn-outline-secondary mb-1 demo-login" data-email="{{ $u }}@hcms.test">{{ $r }}</button>
                        @endforeach
                    </div>
                </div>
                <script>
                    document.querySelectorAll('.demo-login').forEach(b => b.addEventListener('click', () => {
                        document.getElementById('email').value = b.dataset.email;
                        document.getElementById('password').value = 'password';
                    }));
                </script>
            @endif
        </div>
    </div>
</div>
</body>
</html>
