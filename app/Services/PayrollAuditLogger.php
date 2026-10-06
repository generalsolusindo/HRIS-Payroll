<?php

namespace App\Services;

use App\Models\PayrollAuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Records a before/after audit trail for retroactive corrections to
 * payroll-relevant data that no other table already tracks — attendance
 * review corrections (which are also separately versioned on the review
 * itself), salary profile edits, and policy rate changes. Workflow status
 * transitions (submit/approve/revision/lock) go through PayrollApproval
 * instead; this is specifically for "what changed and why" on the
 * underlying numbers.
 */
class PayrollAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        int $companyId,
        string $entityType,
        int $entityId,
        string $action,
        ?array $before,
        ?array $after,
        string $reason,
        ?int $payrollRunId = null,
        ?int $attendanceReviewId = null,
    ): void {
        $actor = Auth::user();

        PayrollAuditLog::create([
            'company_id' => $companyId,
            'payroll_run_id' => $payrollRunId,
            'attendance_review_id' => $attendanceReviewId,
            'actor_id' => $actor?->id,
            'actor_snapshot' => ['name' => $actor !== null ? $actor->name : 'Sistem'],
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'before_values' => $before,
            'after_values' => $after,
            'reason' => $reason,
        ]);
    }
}
