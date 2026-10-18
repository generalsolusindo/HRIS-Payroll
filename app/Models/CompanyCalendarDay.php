<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property Carbon $date
 * @property string $type workday|rest_day|holiday
 * @property array<int, int>|null $allowed_user_ids
 * @property string|null $description
 * @property int|null $work_schedule_id
 * @property array<string, mixed>|null $schedule_snapshot
 */
class CompanyCalendarDay extends Model
{
    protected $fillable = ['company_id', 'date', 'type', 'allowed_user_ids', 'description', 'work_schedule_id', 'schedule_snapshot'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'allowed_user_ids' => 'array',
            'schedule_snapshot' => 'array',
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
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }
}
