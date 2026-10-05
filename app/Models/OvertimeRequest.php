<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $break_minutes
 * @property int|null $approved_minutes
 * @property string $reason
 * @property string $status draft|submitted|approved|rejected|cancelled
 * @property int|null $processed_by
 * @property Carbon|null $processed_at
 * @property string|null $decision_note
 */
class OvertimeRequest extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'starts_at', 'ends_at', 'break_minutes',
        'approved_minutes', 'reason', 'status', 'processed_by', 'processed_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
