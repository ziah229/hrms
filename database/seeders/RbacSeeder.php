<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Daftar permission awal (rancangan bagian 2).
     *
     * @var array<string, array<int, string>>
     */
    public const PERMISSIONS = [
        'dashboard' => ['view'],
        'employee' => ['view', 'create', 'update', 'delete', 'export'],
        'attendance' => ['view', 'create', 'update', 'approve', 'export', 'request'],
        'salary' => ['view', 'create', 'update', 'export'],
        'component' => ['view', 'create', 'update', 'delete'],
        'payroll' => ['view', 'process', 'approve', 'finalize', 'reopen', 'export'],
        'payslip' => ['view', 'view_own', 'export', 'send'],
        'report' => ['view', 'export', 'employee', 'attendance', 'salary', 'component', 'tax', 'bank'],
        'setting' => ['manage'],
        'user' => ['manage'],
        'role' => ['manage'],
        'audit' => ['view'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $module => $actions) {
            foreach ($actions as $action) {
                Permission::updateOrCreate(
                    ['code' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action, 'description' => ucfirst($action).' '.$module]
                );
            }
        }

        $roles = [
            Role::SUPER_ADMIN => [
                'description' => 'Semua menu, manajemen user & role',
                'data_scope' => 'all',
                'permissions' => ['*'],
            ],
            'HR Admin' => [
                'description' => 'Karyawan, absensi, gaji, allowances/deductions, report',
                'data_scope' => 'all',
                'permissions' => [
                    'dashboard.view',
                    'employee.view', 'employee.create', 'employee.update', 'employee.export',
                    'attendance.view', 'attendance.create', 'attendance.update', 'attendance.approve', 'attendance.export', 'attendance.request',
                    'salary.view', 'component.view', 'payroll.view', 'payslip.view',
                    'report.view', 'report.export', 'report.employee', 'report.attendance',
                ],
            ],
            'Payroll Officer' => [
                'description' => 'Gaji, allowances/deductions, register gaji, payslip, report gaji',
                'data_scope' => 'all',
                'permissions' => [
                    'dashboard.view', 'employee.view', 'attendance.view',
                    'salary.view', 'salary.create', 'salary.update', 'salary.export',
                    'component.view', 'component.create', 'component.update', 'component.delete',
                    'payroll.view', 'payroll.process', 'payroll.finalize', 'payroll.export',
                    'payslip.view', 'payslip.export', 'payslip.send',
                    'report.view', 'report.export', 'report.salary', 'report.component', 'report.tax', 'report.bank',
                ],
            ],
            'Manager' => [
                'description' => 'Lihat karyawan & absensi bawahan, report terbatas',
                'data_scope' => 'subordinate',
                'permissions' => [
                    'dashboard.view', 'employee.view', 'attendance.view', 'attendance.approve', 'attendance.request',
                    'report.view', 'report.export', 'report.employee', 'report.attendance',
                ],
            ],
            'Karyawan' => [
                'description' => 'Lihat profil sendiri, absensi sendiri, payslip sendiri',
                'data_scope' => 'self',
                'permissions' => ['dashboard.view', 'employee.view', 'attendance.view', 'attendance.request', 'payslip.view_own'],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(['name' => $name], [
                'description' => $data['description'],
                'data_scope' => $data['data_scope'],
                'is_system' => true,
            ]);

            $ids = $data['permissions'] === ['*']
                ? Permission::pluck('id')
                : Permission::whereIn('code', $data['permissions'])->pluck('id');

            $role->permissions()->sync($ids);
        }
    }
}
