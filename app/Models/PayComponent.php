<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class PayComponent extends Model
{
    public const CALC_METHODS = [
        'nominal' => 'Nominal Tetap',
        'percent' => 'Persentase Gaji Pokok',
        'per_day' => 'Per Hari Kehadiran',
        'formula' => 'Rumus',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_value' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employeeComponents(): HasMany
    {
        return $this->hasMany(EmployeeComponent::class);
    }

    public function adhocComponents(): HasMany
    {
        return $this->hasMany(AdhocComponent::class);
    }
}
