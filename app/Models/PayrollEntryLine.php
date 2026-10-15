<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payroll_entry_id
 * @property int $payroll_component_id
 * @property string $line_key Stable key within an entry — prevents duplicate lines on recalculation retry.
 * @property string $component_code
 * @property string $component_name
 * @property string $type earning|deduction|employer_contribution
 * @property string $quantity
 * @property string $rate
 * @property string $amount
 * @property string $source salary|attendance|overtime|reward|manual|adjustment
 * @property array<string, mixed> $calculation_snapshot
 * @property string|null $adjustment_reason
 * @property int|null $created_by
 */
class PayrollEntryLine extends Model
{
    protected $fillable = [
        'payroll_entry_id', 'payroll_component_id', 'line_key',
        'component_code', 'component_name', 'type',
        'quantity', 'rate', 'amount', 'source', 'calculation_snapshot',
        'adjustment_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'calculation_snapshot' => 'array',
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
     * @return BelongsTo<PayrollComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
