<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LeaveType;
use App\Models\PayComponent;
use App\Models\Position;
use App\Models\ReportDefinition;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'PT Nexa Digital Solusindo',
            'npwp' => '01.345.892.4-012.000',
            'address' => 'Menara Mandiri Tower II Lt. 18-20, Jl. Jendral Sudirman Kav. 52-53, Jakarta Selatan 12190',
            'phone' => '(021) 5299-8800',
        ]);

        $departments = [
            'HR' => 'HR Operations', 'IT' => 'Technology & IT', 'FIN' => 'Finance & Tax',
            'OPS' => 'Operations & Logistik', 'SCM' => 'Supply Chain', 'SLS' => 'Sales & Marketing',
        ];
        foreach ($departments as $code => $name) {
            $company->departments()->create(compact('code', 'name'));
        }

        $positions = [
            ['DIR', 'Direktur', 'L1'], ['MGR', 'Manager', 'L2'], ['SPV', 'Supervisor', 'L3'],
            ['SNR', 'Senior Staff', 'L4'], ['STF', 'Staff', 'L5'], ['INT', 'Magang / Intern', 'L6'],
        ];
        foreach ($positions as [$code, $name, $level]) {
            Position::create(compact('code', 'name', 'level'));
        }

        $company->workLocations()->createMany([
            ['name' => 'Kantor Pusat Jakarta', 'address' => 'Jl. Jendral Sudirman Kav. 52-53, Jakarta'],
            ['name' => 'Gudang Cikarang', 'address' => 'Kawasan Industri Jababeka, Cikarang'],
            ['name' => 'Cabang Surabaya', 'address' => 'Jl. Basuki Rahmat No. 10, Surabaya'],
        ]);

        $company->holidays()->createMany([
            ['holiday_date' => '2026-08-17', 'name' => 'Hari Kemerdekaan RI'],
            ['holiday_date' => '2026-08-25', 'name' => 'Maulid Nabi Muhammad SAW'],
            ['holiday_date' => '2026-12-25', 'name' => 'Hari Raya Natal'],
            ['holiday_date' => '2026-12-26', 'name' => 'Cuti Bersama Natal'],
        ]);

        Shift::insert([
            ['name' => 'Shift Pagi', 'start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_min' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Shift Siang', 'start_time' => '13:00', 'end_time' => '22:00', 'late_tolerance_min' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Shift Fleksibel Lapangan', 'start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_min' => 30, 'created_at' => now(), 'updated_at' => now()],
        ]);

        LeaveType::insert([
            ['name' => 'Cuti Tahunan', 'default_quota' => 12, 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Izin Sakit (SKD)', 'default_quota' => 0, 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cuti Melahirkan', 'default_quota' => 90, 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Izin Keperluan Pribadi', 'default_quota' => 0, 'is_paid' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $components = [
            ['ALW-POS', 'Tunjangan Jabatan', 'allowance', 'fixed', 'nominal', 0, true],
            ['ALW-TRN', 'Tunjangan Transport', 'allowance', 'variable', 'per_day', 35000, true],
            ['ALW-MEL', 'Uang Makan Harian', 'allowance', 'variable', 'per_day', 40000, false],
            ['ALW-COM', 'Tunjangan Komunikasi', 'allowance', 'fixed', 'nominal', 300000, true],
            ['ALW-OVT', 'Lembur / Overtime (Depnaker)', 'allowance', 'variable', 'formula', 0, true],
            ['ALW-BON', 'Bonus / Insentif Prestasi', 'allowance', 'variable', 'nominal', 0, true],
            ['ALW-THR', 'Tunjangan Hari Raya (THR)', 'allowance', 'variable', 'nominal', 0, true],
            ['DED-BPJS-KES', 'BPJS Kesehatan (1% Karyawan)', 'deduction', 'fixed', 'formula', 1, false],
            ['DED-BPJS-TK', 'BPJS Ketenagakerjaan (JHT 2%, JP 1%)', 'deduction', 'fixed', 'formula', 3, false],
            ['DED-PPH21', 'Pemotongan Pajak PPh 21 TER', 'deduction', 'variable', 'formula', 0, false],
            ['DED-LAT', 'Potongan Keterlambatan (per menit)', 'deduction', 'variable', 'formula', 1000, false],
            ['DED-ALP', 'Potongan Alpha (upah harian)', 'deduction', 'variable', 'formula', 0, false],
            ['DED-LOAN', 'Cicilan Kasbon / Pinjaman Koperasi', 'deduction', 'variable', 'formula', 0, false],
        ];
        foreach ($components as [$code, $name, $type, $nature, $method, $value, $taxable]) {
            PayComponent::create([
                'code' => $code, 'name' => $name, 'type' => $type, 'nature' => $nature,
                'calc_method' => $method, 'default_value' => $value, 'is_taxable' => $taxable,
            ]);
        }

        $reports = [
            ['EMP-LIST', 'Daftar Karyawan', 'Karyawan', 'report.employee'],
            ['EMP-HEADCOUNT', 'Headcount per Departemen', 'Karyawan', 'report.employee'],
            ['EMP-CONTRACT', 'Kontrak Akan Berakhir', 'Karyawan', 'report.employee'],
            ['ATT-MONTHLY', 'Rekap Absensi Bulanan', 'Absensi', 'report.attendance'],
            ['ATT-LATE', 'Laporan Keterlambatan', 'Absensi', 'report.attendance'],
            ['ATT-LEAVE', 'Rekap Cuti & Izin', 'Absensi', 'report.attendance'],
            ['PAY-REGISTER', 'Register Gaji', 'Gaji', 'report.salary'],
            ['PAY-DEPT', 'Rekap Gaji per Departemen', 'Gaji', 'report.salary'],
            ['CMP-RECAP', 'Rekap Allowance & Deduction', 'Allowance/Deduction', 'report.component'],
            ['CMP-LOAN', 'Saldo Pinjaman', 'Allowance/Deduction', 'report.component'],
            ['TAX-PPH21', 'Rekap PPh 21', 'Pajak & BPJS', 'report.tax'],
            ['TAX-BPJS', 'Rekap BPJS', 'Pajak & BPJS', 'report.tax'],
            ['BANK-TRANSFER', 'Daftar Transfer Gaji per Bank', 'Transfer Bank', 'report.bank'],
        ];
        foreach ($reports as [$code, $name, $category, $permission]) {
            $params = match ($category) {
                'Karyawan' => ['department', 'status'],
                'Absensi' => ['month', 'department'],
                default => ['period', 'department'],
            };
            ReportDefinition::create(compact('code', 'name', 'category', 'permission') + ['parameters' => $params]);
        }
    }
}
