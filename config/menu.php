<?php

/*
|--------------------------------------------------------------------------
| Struktur Menu Sidebar (rancangan bagian 3)
|--------------------------------------------------------------------------
|
| Item tanpa permission yang dimiliki user tidak ditampilkan. "can" berisi satu
| atau beberapa permission (cukup salah satu). "active" adalah pola nama route.
|
*/

return [
    ['section' => 'Utama'],
    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'can' => 'dashboard.view', 'active' => 'dashboard', 'live' => true],

    ['section' => 'Kepegawaian'],
    ['label' => 'Data Karyawan', 'icon' => 'group', 'can' => 'employee.view', 'active' => ['employees.*'], 'children' => [
        ['label' => 'Daftar Karyawan', 'route' => 'employees.index', 'can' => 'employee.view'],
        // ['label' => 'Tambah Karyawan', 'route' => 'employees.index', 'query' => ['create' => 1], 'can' => 'employee.create'],
        ['label' => 'Master Departemen', 'route' => 'master.index', 'params' => ['type' => 'departments'], 'can' => 'employee.update'],
        ['label' => 'Master Jabatan', 'route' => 'master.index', 'params' => ['type' => 'positions'], 'can' => 'employee.update'],
        ['label' => 'Lokasi Kerja', 'route' => 'master.index', 'params' => ['type' => 'work-locations'], 'can' => 'employee.update'],
    ]],
    ['label' => 'Absensi Karyawan', 'icon' => 'event_available', 'can' => 'attendance.view', 'active' => ['attendance.*'], 'children' => [
        ['label' => 'Rekap Harian', 'route' => 'attendance.index', 'can' => 'attendance.view'],
        ['label' => 'Cuti & Izin', 'route' => 'attendance.index', 'query' => ['tab' => 'leave'], 'can' => 'attendance.view'],
        ['label' => 'Lembur', 'route' => 'attendance.index', 'query' => ['tab' => 'overtime'], 'can' => 'attendance.view'],
        ['label' => 'Jadwal / Shift', 'route' => 'master.index', 'params' => ['type' => 'shifts'], 'can' => 'attendance.update'],
        ['label' => 'Jenis Cuti', 'route' => 'master.index', 'params' => ['type' => 'leave-types'], 'can' => 'attendance.update'],
    ]],

    ['section' => 'Kompensasi & Benefit'],
    ['label' => 'Data Gaji', 'icon' => 'account_balance_wallet', 'can' => 'salary.view', 'active' => ['salaries.*'], 'children' => [
        ['label' => 'Gaji Pokok', 'route' => 'salaries.index', 'can' => 'salary.view'],
        ['label' => 'Riwayat Perubahan', 'route' => 'salaries.index', 'query' => ['view' => 'history'], 'can' => 'salary.view'],
    ]],
    ['label' => 'Allowances & Deductions', 'icon' => 'sell', 'can' => 'component.view', 'active' => ['components.*'], 'children' => [
        ['label' => 'Master Komponen', 'route' => 'components.index', 'can' => 'component.view'],
        ['label' => 'Komponen Karyawan', 'route' => 'components.index', 'query' => ['tab' => 'employee'], 'can' => 'component.view'],
        ['label' => 'Sekali Bayar', 'route' => 'components.index', 'query' => ['tab' => 'adhoc'], 'can' => 'component.view'],
        ['label' => 'Kasbon & Pinjaman', 'route' => 'components.index', 'query' => ['tab' => 'loan'], 'can' => 'component.view'],
    ]],
    ['label' => 'Register Gaji', 'icon' => 'receipt_long', 'can' => 'payroll.view', 'active' => ['payroll.*'], 'children' => [
        ['label' => 'Periode & Review', 'route' => 'payroll.index', 'can' => 'payroll.view'],
    ]],
    ['label' => 'Payslip', 'icon' => 'payments', 'can' => ['payslip.view', 'payslip.view_own'], 'active' => ['payslips.*'], 'children' => [
        ['label' => 'Daftar Payslip', 'route' => 'payslips.index', 'can' => 'payslip.view'],
        ['label' => 'Payslip Saya', 'route' => 'payslips.mine', 'can' => 'payslip.view_own'],
    ]],

    ['section' => 'Laporan & Sistem'],
    ['label' => 'Report', 'icon' => 'bar_chart', 'route' => 'reports.index', 'can' => 'report.view', 'active' => 'reports.*'],
    ['label' => 'Pengaturan', 'icon' => 'settings', 'can' => ['user.manage', 'role.manage', 'setting.manage', 'audit.view'], 'active' => ['settings.*'], 'children' => [
        ['label' => 'User', 'route' => 'settings.users.index', 'can' => 'user.manage'],
        ['label' => 'Role & Permission', 'route' => 'settings.roles.index', 'can' => 'role.manage'],
        ['label' => 'Perusahaan', 'route' => 'settings.company.edit', 'can' => 'setting.manage'],
        ['label' => 'Hari Libur', 'route' => 'master.index', 'params' => ['type' => 'holidays'], 'can' => 'setting.manage'],
        ['label' => 'Audit Log', 'route' => 'settings.audit-logs', 'can' => 'audit.view'],
    ]],
];
