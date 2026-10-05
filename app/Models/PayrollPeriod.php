<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon $attendance_starts_on
 * @property Carbon $attendance_ends_on
 * @property Carbon|null $payment_due_on
 * @property string $currency
 * @property string $status open|closed
 */
class PayrollPeriod extends Model
{
    protected $fillable = [
        'company_id', 'code', 'name', 'starts_on', 'ends_on',
        'attendance_starts_on', 'attendance_ends_on', 'payment_due_on',
        'currency', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'attendance_starts_on' => 'date',
            'attendance_ends_on' => 'date',
            'payment_due_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
