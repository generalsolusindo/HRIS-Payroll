<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property Carbon $date
 * @property int|null $attendance_id
 * @property string $status draft|validated
 * @property string $classification unresolved|present|half_day|leave|sick|permission|business_trip|absent|rest_day|holiday
 * @property Carbon|null $clock_in_at
 * @property Carbon|null $clock_out_at
 * @property int $scheduled_minutes
 * @property int $worked_minutes
 * @property int $late_minutes
 * @property int $early_departure_minutes
 * @property int $excused_minutes
 * @property array<string, mixed> $source_snapshot
 * @property array<string, mixed>|null $leave_snapshot
 * @property array<string, mixed> $schedule_snapshot
 * @property string|null $correction_reason
 * @property string|null $evidence_path
 * @property int $version
 * @property int|null $validated_by
 * @property Carbon|null $validated_at
 */
class AttendanceReview extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'date', 'attendance_id', 'status', 'classification',
        'clock_in_at', 'clock_out_at', 'scheduled_minutes', 'worked_minutes',
        'late_minutes', 'early_departure_minutes', 'excused_minutes',
        'source_snapshot', 'leave_snapshot', 'schedule_snapshot',
        'correction_reason', 'evidence_path', 'version', 'validated_by', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'source_snapshot' => 'array',
            'leave_snapshot' => 'array',
            'schedule_snapshot' => 'array',
            'validated_at' => 'datetime',
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
     * @return BelongsTo<Attendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
