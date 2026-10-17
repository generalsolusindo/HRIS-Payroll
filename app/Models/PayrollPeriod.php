<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon $attendance_starts_on
 * @property Carbon $attendance_ends_on
 * @property Carbon|null $payment_due_on
 * @property Carbon|null $archived_at
 * @property string $currency
 * @property string $status open|closed
 */
class PayrollPeriod extends Model
{
    protected $fillable = [
        'company_id', 'name', 'starts_on', 'ends_on',
        'attendance_starts_on', 'attendance_ends_on', 'payment_due_on',
        'currency', 'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'attendance_starts_on' => 'date',
            'attendance_ends_on' => 'date',
            'payment_due_on' => 'date',
            'archived_at' => 'datetime',
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

    /**
     * @return HasMany<PayrollPeriodDeletionRequest, $this>
     */
    public function deletionRequests(): HasMany
    {
        return $this->hasMany(PayrollPeriodDeletionRequest::class);
    }
}
