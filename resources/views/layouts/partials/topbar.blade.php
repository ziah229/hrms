@php
    $user = auth()->user();
    $pendingApprovals = $user->hasPermission('attendance.approve')
        ? \App\Models\LeaveRequest::where('status', 'pending')->count() + \App\Models\OvertimeRequest::where('status', 'pending')->count()
        : 0;
@endphp
<header class="app-topbar">
    <button class="icon-btn" id="sidebarToggle" type="button" aria-label="Toggle menu">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <nav aria-label="breadcrumb" class="d-none d-md-block">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            @yield('breadcrumb')
        </ol>
    </nav>

    <form class="topbar-search position-relative d-none d-lg-block ms-2" action="{{ route('employees.index') }}" method="get">
        <span class="material-symbols-outlined">search</span>
        <input type="search" name="q" class="form-control" placeholder="Cari karyawan (nama / NIK)..." value="{{ request('q') }}">
    </form>

    <div class="ms-auto d-flex align-items-center gap-2">
        @if ($user->hasPermission('attendance.approve'))
            <a href="{{ route('attendance.index', ['tab' => 'leave']) }}" class="icon-btn" title="Pengajuan menunggu approval">
                <span class="material-symbols-outlined">notifications</span>
                @if ($pendingApprovals)<span class="dot-count bg-danger">{{ $pendingApprovals }}</span>@endif
            </a>
        @endif
        <span class="role-pill d-none d-sm-inline">{{ $user->role?->name }} · {{ ucfirst($user->role?->data_scope ?? '-') }}</span>
        <div class="dropdown">
            <button class="btn p-0 border-0 d-flex align-items-center gap-1" data-bs-toggle="dropdown" type="button">
                <span class="avatar">{{ $user->employee?->initials() ?? strtoupper(substr($user->name, 0, 2)) }}</span>
                <span class="material-symbols-outlined text-muted-hc">arrow_drop_down</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="px-3 py-2">
                    <div class="fw-600">{{ $user->name }}</div>
                    <div class="fs-xs text-muted-hc">{{ $user->email }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                @if ($user->employee)
                    <li><a class="dropdown-item" href="{{ route('employees.show', $user->employee) }}"><span class="material-symbols-outlined ms-sm me-2">badge</span>Profil Saya</a></li>
                @endif
                @if ($user->hasPermission('payslip.view_own'))
                    <li><a class="dropdown-item" href="{{ route('payslips.mine') }}"><span class="material-symbols-outlined ms-sm me-2">payments</span>Payslip Saya</a></li>
                @endif
                <li>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger"><span class="material-symbols-outlined ms-sm me-2">logout</span>Keluar</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
