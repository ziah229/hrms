<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Shift;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Hitung keterlambatan, pulang cepat, dan jam kerja dari jam masuk/pulang terhadap shift.
     *
     * @return array<string, mixed>
     */
    public function compute(?Shift $shift, ?string $checkIn, ?string $checkOut, ?string $status = null): array
    {
        $late = $early = 0;
        $hours = 0;

        if ($shift && $checkIn) {
            $start = Carbon::parse($shift->start_time)->addMinutes($shift->late_tolerance_min);
            $in = Carbon::parse($checkIn);
            $late = $in->gt($start) ? (int) Carbon::parse($shift->start_time)->diffInMinutes($in) : 0;
        }
        if ($shift && $checkOut) {
            $end = Carbon::parse($shift->end_time);
            $out = Carbon::parse($checkOut);
            $early = $out->lt($end) ? (int) $out->diffInMinutes($end) : 0;
        }
        if ($checkIn && $checkOut) {
            // Dikurangi 1 jam istirahat.
            $hours = max(0, round(Carbon::parse($checkIn)->diffInMinutes(Carbon::parse($checkOut)) / 60 - 1, 2));
        }

        if (! $status || in_array($status, ['hadir', 'terlambat'])) {
            $status = $checkIn ? ($late > 0 ? 'terlambat' : 'hadir') : 'alpha';
        }

        return [
            'late_minutes' => $late,
            'early_leave_minutes' => $early,
            'work_hours' => $hours,
            'status' => $status,
        ];
    }

    /**
     * Tulis status absensi untuk rentang tanggal (dipakai saat cuti/izin disetujui).
     */
    public function markRange(int $employeeId, Carbon $from, Carbon $to, string $status, string $notes): void
    {
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }
            Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'attendance_date' => $d->toDateString()],
                ['status' => $status, 'source' => 'manual', 'notes' => $notes, 'check_in' => null, 'check_out' => null,
                    'late_minutes' => 0, 'early_leave_minutes' => 0, 'work_hours' => 0]
            );
        }
    }
}
