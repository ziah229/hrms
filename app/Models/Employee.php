<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded(['id'])]
class Employee extends Model
{
    use SoftDeletes;

    public const EMPLOYMENT_STATUSES = [
        'tetap' => 'Karyawan Tetap (PKWTT)',
        'kontrak' => 'Kontrak (PKWT)',
        'magang' => 'Magang',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'resign_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function primaryBank(): HasOne
    {
        return $this->hasOne(EmployeeBankAccount::class)->where('is_primary', true);
    }

    public function families(): HasMany
    {
        return $this->hasMany(EmployeeFamily::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(EmployeeComponent::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Gaji pokok yang berlaku pada tanggal tertentu (riwayat tidak ditimpa).
     */
    public function salaryAt(string $date): ?EmployeeSalary
    {
        return $this->salaries()
            ->where('effective_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->latest('effective_date')
            ->first();
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->full_name));

        return strtoupper(substr($words[0], 0, 1).substr($words[1] ?? '', 0, 1));
    }

    /**
     * Batasi query sesuai data scope role user yang login.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->role?->data_scope ?? 'self';
        $self = $user->employee;

        return match ($scope) {
            'all' => $query,
            'department' => $query->where('department_id', $self?->department_id),
            'subordinate' => $query->whereIn('id', $self ? $self->subordinateIds(true) : []),
            default => $query->where('id', $self?->id),
        };
    }

    /**
     * ID bawahan langsung maupun tidak langsung.
     *
     * @return array<int, int>
     */
    public function subordinateIds(bool $includeSelf = false): array
    {
        $ids = $includeSelf ? [$this->id] : [];
        $level = [$this->id];

        while ($level) {
            $level = static::whereIn('manager_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }
}
