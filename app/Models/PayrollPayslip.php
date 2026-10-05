<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_entry_id
 * @property string $number
 * @property int $version
 * @property string $status draft|published|superseded
 * @property string|null $file_path
 * @property string|null $file_sha256
 * @property array<string, mixed> $snapshot
 * @property int|null $published_by
 * @property Carbon|null $published_at
 */
class PayrollPayslip extends Model
{
    protected $fillable = [
        'payroll_entry_id', 'number', 'version', 'status',
        'file_path', 'file_sha256', 'snapshot', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
