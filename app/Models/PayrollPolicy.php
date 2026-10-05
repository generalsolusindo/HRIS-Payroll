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
 * @property int $version
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property array<string, mixed> $rules Declarative parameters only — never executable PHP/SQL.
 * @property string|null $description
 * @property int|null $created_by
 */
class PayrollPolicy extends Model
{
    protected $fillable = [
        'company_id', 'code', 'version', 'effective_from', 'effective_until',
        'rules', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'rules' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
