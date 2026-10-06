<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollAuditLogController extends Controller
{
    /**
     * Read-only trail of retroactive corrections to payroll-relevant data
     * (attendance review corrections, salary profile edits, policy rate
     * changes) — see PayrollAuditLogger. Workflow transitions like
     * submit/approve/lock live in payroll_approvals instead and are shown
     * on the run page itself, not here.
     */
    public function index(Request $request): Response
    {
        $logs = PayrollAuditLog::with('company:id,name', 'actor:id,name')
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->string('entity_type')->toString()))
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->integer('company_id')))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (PayrollAuditLog $log) => [
                'id' => $log->id,
                'company_name' => $log->company->name,
                'actor_name' => $log->actor !== null ? $log->actor->name : ($log->actor_snapshot['name'] ?? 'Sistem'),
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'action' => $log->action,
                'before_values' => $log->before_values,
                'after_values' => $log->after_values,
                'reason' => $log->reason,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('hrd/audit-log', [
            'logs' => $logs,
            'entityTypes' => PayrollAuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('entity_type', 'company_id'),
        ]);
    }
}
