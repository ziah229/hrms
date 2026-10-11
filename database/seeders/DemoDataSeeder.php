<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\PayrollService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $faker->seed(2026);
        mt_srand(2026);

        $dept = Department::pluck('id', 'code');
        $pos = Position::pluck('id', 'code');
        $locations = WorkLocation::pluck('id')->all();
        $banks = ['BCA', 'Bank Mandiri', 'BNI', 'BRI', 'CIMB Niaga'];
        $ptkp = ['TK/0', 'TK/1', 'K/0', 'K/1', 'K/2', 'K/3'];
        $salaryByPos = ['DIR' => 45_000_000, 'MGR' => 24_000_000, 'SPV' => 12_500_000, 'SNR' => 9_500_000, 'STF' => 6_500_000, 'INT' => 3_500_000];
        $positionAllowance = ['DIR' => 10_000_000, 'MGR' => 5_000_000, 'SPV' => 2_500_000, 'SNR' => 1_500_000, 'STF' => 500_000, 'INT' => 0];

        $no = 0;
        $make = function (array $attrs) use (&$no, $faker, $locations, $banks, $ptkp) {
            $no++;
            $gender = $attrs['gender'] ?? $faker->randomElement(['L', 'P']);
            $name = $attrs['full_name'] ?? $faker->name($gender === 'L' ? 'male' : 'female');
            $status = $attrs['employment_status'] ?? $faker->randomElement(['tetap', 'tetap', 'tetap', 'kontrak']);
            $attrs = array_merge($attrs, ['full_name' => $name, 'gender' => $gender, 'employment_status' => $status]);

            $employee = Employee::create($attrs + [
                'employee_no' => sprintf('EMP-%s-%03d', '2026', $no),
                'nik_ktp' => '3271'.$faker->numerify('############'),
                'npwp' => $faker->numerify('################'),
                'birth_place' => $faker->city(),
                'birth_date' => $faker->dateTimeBetween('-50 years', '-21 years'),
                'religion' => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
                'marital_status' => $faker->randomElement(['Lajang', 'Menikah']),
                'ptkp_status' => $faker->randomElement($ptkp),
                'phone' => '08'.$faker->numerify('##########'),
                'email' => strtolower(str_replace([' ', '.', ','], '', explode(' ', $name)[0])).$no.'@nexa.co.id',
                'address' => $faker->address(),
                'work_location_id' => $faker->randomElement($locations),
                'join_date' => $faker->dateTimeBetween('-8 years', '-2 months'),
                'bpjs_kesehatan_no' => '000'.$faker->numerify('##########'),
                'bpjs_tk_no' => $faker->numerify('###########'),
                'is_active' => true,
            ]);

            $employee->bankAccounts()->create([
                'bank_name' => $faker->randomElement($banks),
                'account_no' => $faker->numerify('##########'),
                'account_name' => $employee->full_name,
                'is_primary' => true,
            ]);

            if ($status !== 'tetap') {
                $employee->contracts()->create([
                    'contract_type' => $status === 'kontrak' ? 'PKWT' : 'Magang',
                    'start_date' => $employee->join_date,
                    // Sebagian kontrak berakhir dalam 30 hari agar muncul di pengingat dashboard.
                    'end_date' => mt_rand(0, 2) === 0 ? now()->addDays(mt_rand(5, 30)) : now()->addMonths(mt_rand(2, 12)),
                ]);
            }

            return $employee;
        };

        // Karyawan inti yang terhubung dengan akun login.
        $director = $make(['full_name' => 'Hartanto Prasetyo', 'gender' => 'L', 'department_id' => $dept['FIN'], 'position_id' => $pos['DIR'], 'employment_status' => 'tetap']);
        $hrLead = $make(['full_name' => 'Dewi Kartika', 'gender' => 'P', 'department_id' => $dept['HR'], 'position_id' => $pos['MGR'], 'manager_id' => $director->id, 'employment_status' => 'tetap']);
        $payrollOfficer = $make(['full_name' => 'Bambang Sutrisno', 'gender' => 'L', 'department_id' => $dept['FIN'], 'position_id' => $pos['SPV'], 'manager_id' => $director->id, 'employment_status' => 'tetap']);
        $itManager = $make(['full_name' => 'Budi Prasetyo', 'gender' => 'L', 'department_id' => $dept['IT'], 'position_id' => $pos['MGR'], 'manager_id' => $director->id, 'employment_status' => 'tetap']);
        $staff = $make(['full_name' => 'Arya Pratama', 'gender' => 'L', 'department_id' => $dept['IT'], 'position_id' => $pos['STF'], 'manager_id' => $itManager->id, 'employment_status' => 'tetap']);

        $managers = ['HR' => $hrLead, 'IT' => $itManager, 'FIN' => $payrollOfficer];
        foreach (['OPS', 'SCM', 'SLS'] as $code) {
            $managers[$code] = $make(['department_id' => $dept[$code], 'position_id' => $pos['MGR'], 'manager_id' => $director->id, 'employment_status' => 'tetap']);
        }

        foreach (range(1, 34) as $i) {
            $code = array_rand($managers);
            $posCode = $faker->randomElement(['SPV', 'SNR', 'STF', 'STF', 'STF', 'INT']);
            $make([
                'department_id' => $dept[$code],
                'position_id' => $pos[$posCode],
                'manager_id' => $managers[$code]->id,
                'employment_status' => $posCode === 'INT' ? 'magang' : null,
            ]);
        }

        // Akun login demo (password: password).
        $roles = Role::pluck('id', 'name');
        $accounts = [
            ['Super Administrator', 'superadmin@hcms.test', Role::SUPER_ADMIN, $director],
            ['Dewi Kartika', 'hr@hcms.test', 'HR Admin', $hrLead],
            ['Bambang Sutrisno', 'payroll@hcms.test', 'Payroll Officer', $payrollOfficer],
            ['Budi Prasetyo', 'manager@hcms.test', 'Manager', $itManager],
            ['Arya Pratama', 'karyawan@hcms.test', 'Karyawan', $staff],
        ];
        foreach ($accounts as [$name, $email, $role, $employee]) {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'role_id' => $roles[$role]]);
            $employee->update(['user_id' => $user->id]);
        }

        // Gaji pokok, riwayat kenaikan, dan komponen tetap.
        $components = PayComponent::pluck('id', 'code');
        foreach (Employee::with('position')->get() as $employee) {
            $base = $salaryByPos[$employee->position->code] * (1 + mt_rand(-10, 15) / 100);
            $base = round($base, -5);
            if ($employee->join_date->lt('2026-01-01')) {
                $employee->salaries()->create([
                    'basic_salary' => round($base * 0.9, -5), 'salary_type' => 'monthly',
                    'effective_date' => $employee->join_date, 'end_date' => '2025-12-31', 'reason' => 'Gaji awal',
                ]);
                $employee->salaries()->create([
                    'basic_salary' => $base, 'salary_type' => 'monthly',
                    'effective_date' => '2026-01-01', 'reason' => 'Penyesuaian tahunan 2026',
                ]);
            } else {
                $employee->salaries()->create([
                    'basic_salary' => $base, 'salary_type' => 'monthly',
                    'effective_date' => $employee->join_date, 'reason' => 'Gaji awal',
                ]);
            }

            $assign = [
                'ALW-TRN' => 35000, 'ALW-MEL' => 40000,
            ];
            if ($positionAllowance[$employee->position->code] > 0) {
                $assign['ALW-POS'] = $positionAllowance[$employee->position->code];
            }
            if (in_array($employee->position->code, ['DIR', 'MGR', 'SPV'])) {
                $assign['ALW-COM'] = 300000;
            }
            foreach ($assign as $code => $value) {
                $employee->components()->create([
                    'pay_component_id' => $components[$code], 'value' => $value, 'start_date' => max('2026-01-01', $employee->join_date->toDateString()),
                ]);
            }
        }

        // Absensi Agustus 2026 s/d hari ini.
        $shiftId = Shift::where('name', 'Shift Pagi')->value('id');
        $holidays = Holiday::pluck('holiday_date')->map->toDateString()->all();
        $employees = Employee::pluck('id')->all();
        $rows = [];
        foreach (CarbonPeriod::create('2026-08-01', now()->toDateString()) as $day) {
            if ($day->isWeekend() || in_array($day->toDateString(), $holidays)) {
                continue;
            }
            foreach ($employees as $id) {
                $rows[] = $this->attendanceRow($id, $shiftId, $day);
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Attendance::insert($chunk);
        }

        // Pengajuan cuti & lembur.
        $types = LeaveType::pluck('id')->all();
        foreach ($faker->randomElements($employees, 8) as $i => $id) {
            $start = Carbon::parse('2026-10-05')->addDays($i * 2);
            LeaveRequest::create([
                'employee_id' => $id, 'leave_type_id' => $faker->randomElement($types),
                'start_date' => $start, 'end_date' => $start->copy()->addDays(1), 'total_days' => 2,
                'reason' => $faker->randomElement(['Acara keluarga', 'Kontrol kesehatan', 'Liburan keluarga', 'Keperluan pribadi']),
                'status' => $i < 5 ? 'pending' : 'approved',
                'approved_at' => $i < 5 ? null : now(),
            ]);
        }
        foreach ($faker->randomElements($employees, 12) as $i => $id) {
            OvertimeRequest::create([
                'employee_id' => $id,
                'overtime_date' => Carbon::parse('2026-09-01')->addDays($i * 2),
                'hours' => $faker->randomElement([2, 3, 4, 6.5]),
                'reason' => $faker->randomElement(['Closing laporan bulanan', 'Stock opname gudang', 'Deployment sistem', 'Audit eksternal']),
                'status' => $i < 9 ? 'approved' : 'pending',
            ]);
        }

        // Kasbon.
        foreach ($faker->randomElements($employees, 3) as $id) {
            Loan::create([
                'employee_id' => $id, 'total_amount' => 6_000_000, 'tenor_months' => 10,
                'installment_amount' => 600_000, 'remaining_amount' => 6_000_000,
                'start_date' => '2026-08-01', 'status' => 'active',
            ]);
        }

        // Periode Agustus (final + payslip) dan September (siap review).
        $service = app(PayrollService::class);
        $august = PayrollPeriod::create([
            'name' => 'Agustus 2026', 'month' => 8, 'year' => 2026,
            'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'pay_date' => '2026-08-28', 'status' => 'draft',
        ]);
        $service->process($august);
        $service->approve($august);
        $service->finalize($august);

        $september = PayrollPeriod::create([
            'name' => 'September 2026', 'month' => 9, 'year' => 2026,
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'pay_date' => '2026-09-28', 'status' => 'draft',
        ]);
        $service->process($september);

        PayrollPeriod::create([
            'name' => 'Oktober 2026', 'month' => 10, 'year' => 2026,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'pay_date' => '2026-10-28', 'status' => 'draft',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attendanceRow(int $employeeId, int $shiftId, Carbon $day): array
    {
        $roll = mt_rand(1, 100);
        $status = match (true) {
            $roll <= 84 => 'hadir',
            $roll <= 92 => 'terlambat',
            $roll <= 94 => 'izin',
            $roll <= 96 => 'sakit',
            $roll <= 98 => 'cuti',
            $roll <= 99 => 'alpha',
            default => 'dinas',
        };

        $in = $out = null;
        $late = 0;
        if (in_array($status, ['hadir', 'terlambat', 'dinas'])) {
            $late = $status === 'terlambat' ? mt_rand(6, 45) : 0;
            $inTime = $day->copy()->setTime(7, 40)->addMinutes($status === 'terlambat' ? 20 + $late : mt_rand(0, 24));
            $outTime = $day->copy()->setTime(17, 0)->addMinutes(mt_rand(0, 60));
            $in = $inTime->format('H:i:s');
            $out = $day->isToday() ? null : $outTime->format('H:i:s');
        }

        return [
            'employee_id' => $employeeId,
            'shift_id' => $shiftId,
            'attendance_date' => $day->toDateString(),
            'check_in' => $in,
            'check_out' => $out,
            'late_minutes' => $late,
            'early_leave_minutes' => 0,
            'work_hours' => $in && $out ? round((strtotime($out) - strtotime($in)) / 3600 - 1, 2) : 0,
            'status' => $status,
            'source' => $status === 'dinas' ? 'gps' : 'fingerprint',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
