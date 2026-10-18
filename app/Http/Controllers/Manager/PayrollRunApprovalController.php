<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollApproval;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodDeletionRequest;
use App\Models\PayrollRun;
use App\Services\PayrollReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunApprovalController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        $runs = PayrollRun::where('status', 'submitted')
            ->whereHas('period', fn ($query) => $query
                ->where('company_id', $request->user()->company_id)
                ->whereNull('archived_at'))
            ->with('period.company')
            ->oldest('submitted_at')
            ->get()
            ->map(fn (PayrollRun $run) => [
                'id' => $run->id,
                'version' => $run->version,
                'employee_count' => $run->employee_count,
                'gross_total' => (string) $run->gross_total,
                'deduction_total' => (string) $run->deduction_total,
                'reward_total' => (string) $run->reward_total,
                'net_total' => (string) $run->net_total,
                'submitted_at' => $run->submitted_at?->toIso8601String(),
                'period' => [
                    'name' => $run->period->name,
                    'company_name' => $run->period->company->name,
                    'starts_on' => $run->period->starts_on->toDateString(),
                    'ends_on' => $run->period->ends_on->toDateString(),
                ],
            ]);

        $deletionRequests = PayrollPeriodDeletionRequest::where('status', 'pending')
            ->whereHas('period', fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->with(['period', 'requester'])
            ->oldest('requested_at')
            ->get()
            ->map(fn (PayrollPeriodDeletionRequest $deletionRequest) => [
                'id' => $deletionRequest->id,
                'reason' => $deletionRequest->reason,
                'requested_at' => $deletionRequest->requested_at->toIso8601String(),
                'requester_name' => $deletionRequest->requester?->name
                    ?? ($deletionRequest->requested_by_snapshot['name'] ?? 'HRD'),
                'period' => [
                    'id' => $deletionRequest->period->id,
                    'name' => $deletionRequest->period->name,
                    'starts_on' => $deletionRequest->period->starts_on->toDateString(),
                    'ends_on' => $deletionRequest->period->ends_on->toDateString(),
                    'run_count' => $deletionRequest->period->runs()->count(),
                ],
            ]);

        return Inertia::render('manager/payroll-run-approval', [
            'runs' => $runs,
            'deletionRequests' => $deletionRequests,
        ]);
    }

    /**
     * A Manager's own detail view — same breakdown HRD sees (read-only
     * here, no draft-stage actions like Perbarui Draft/Batalkan/Bagikan),
     * plus the Setujui/Minta Revisi actions that were previously only
     * reachable from the list card, never from "Lihat Detail".
     */
    public function show(Request $request, PayrollRun $run): Response
    {
        $this->assertOwnCompany($request, $run->period->company_id);

        $run->load('period.company', 'entries.division', 'entries.lines', 'approvals.actor');

        return Inertia::render('manager/payroll-run-show', [
            'readiness_issues' => (new PayrollReadiness)->issues($run),
            'capabilities' => [
                'approve' => $run->status === 'submitted',
                'requestRevision' => $run->status === 'submitted',
            ],
            'divisions' => $run->entries->groupBy('division_id')->map(fn ($entries) => [
                'id' => $entries->first()->division_id,
                'name' => $entries->first()->division_name,
                'count' => $entries->count(),
                'net_total' => $entries->reduce(fn (string $total, PayrollEntry $entry) => bcadd($total, (string) $entry->net_amount, 2), '0.00'),
            ])->values(),
            'run' => [
                'id' => $run->id,
                'version' => $run->version,
                'status' => $run->status,
                'employee_count' => $run->employee_count,
                'gross_total' => (string) $run->gross_total,
                'deduction_total' => (string) $run->deduction_total,
                'reward_total' => (string) $run->reward_total,
                'net_total' => (string) $run->net_total,
                'revision_reason' => $run->revision_reason,
                'period' => [
                    'id' => $run->period->id,
                    'name' => $run->period->name,
                    'company_name' => $run->period->company->name,
                    'starts_on' => $run->period->starts_on->toDateString(),
                    'ends_on' => $run->period->ends_on->toDateString(),
                    'attendance_starts_on' => $run->period->attendance_starts_on->toDateString(),
                    'attendance_ends_on' => $run->period->attendance_ends_on->toDateString(),
                ],
            ],
            'approvals' => $run->approvals->sortBy('created_at')->values()->map(fn (PayrollApproval $approval) => [
                'id' => $approval->id,
                'action' => $approval->action,
                'actor_name' => $approval->actor !== null ? $approval->actor->name : ($approval->actor_snapshot['name'] ?? 'Sistem'),
                'note' => $approval->note,
                'created_at' => $approval->created_at->toIso8601String(),
            ]),
            'entries' => $run->entries->map(fn (PayrollEntry $entry) => [
                'id' => $entry->id,
                'name' => $entry->employee_snapshot['name'] ?? '—',
                'division_id' => $entry->division_id,
                'division_name' => $entry->division_name,
                'position_name' => $entry->position_name,
                'gross_amount' => (string) $entry->gross_amount,
                'deduction_amount' => (string) $entry->deduction_amount,
                'reward_amount' => (string) $entry->reward_amount,
                'net_amount' => (string) $entry->net_amount,
                'unresolved_days' => $entry->attendance_snapshot['unresolved_days'] ?? 0,
                'lines' => $entry->lines->map(fn (PayrollEntryLine $line) => [
                    'id' => $line->id,
                    'source' => $line->source,
                    'component_name' => $line->component_name,
                    'type' => $line->type,
                    'amount' => (string) $line->amount,
                ]),
            ]),
        ]);
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->assertOwnCompany($request, $run->period->company_id);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->transition($request, $run, 'submitted', 'approved', 'approved', $validated['note'] ?? null, ['approved_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hasil payroll disetujui.']);

        return back();
    }

    public function requestRevision(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->assertOwnCompany($request, $run->period->company_id);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $this->transition($request, $run, 'submitted', 'revision_requested', 'revision_requested', $validated['note'], ['revision_reason' => $validated['note']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan revisi dikirim ke HRD.']);

        return back();
    }

    public function approvePeriodDeletion(Request $request, PayrollPeriodDeletionRequest $deletionRequest): RedirectResponse
    {
        $this->assertOwnCompany($request, $deletionRequest->period->company_id);

        DB::transaction(function () use ($request, $deletionRequest) {
            $lockedRequest = PayrollPeriodDeletionRequest::whereKey($deletionRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $period = PayrollPeriod::whereKey($lockedRequest->payroll_period_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== 'pending' || $period->archived_at !== null) {
                throw ValidationException::withMessages([
                    'status' => 'Permintaan ini sudah diproses atau periode telah diarsipkan.',
                ]);
            }

            $period->forceFill(['archived_at' => now()])->save();
            $lockedRequest->fill([
                'status' => 'approved',
                'decided_by' => $request->user()->id,
                'decided_by_snapshot' => ['name' => $request->user()->name],
                'decided_at' => now(),
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Periode disetujui untuk diarsipkan. Riwayat payroll tetap tersimpan.']);

        return back();
    }

    public function rejectPeriodDeletion(Request $request, PayrollPeriodDeletionRequest $deletionRequest): RedirectResponse
    {
        $this->assertOwnCompany($request, $deletionRequest->period->company_id);
        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $deletionRequest, $validated) {
            $lockedRequest = PayrollPeriodDeletionRequest::whereKey($deletionRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Permintaan ini sudah diproses.',
                ]);
            }

            $lockedRequest->fill([
                'status' => 'rejected',
                'decided_by' => $request->user()->id,
                'decided_by_snapshot' => ['name' => $request->user()->name],
                'decision_note' => $validated['decision_note'],
                'decided_at' => now(),
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan penghapusan ditolak.']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    private function transition(Request $request, PayrollRun $run, string $expectedStatus, string $toStatus, string $action, ?string $note, array $extraAttributes): void
    {
        DB::transaction(function () use ($request, $run, $expectedStatus, $toStatus, $action, $note, $extraAttributes) {
            Company::whereKey($run->period->company_id)->lockForUpdate()->firstOrFail();
            $period = PayrollPeriod::whereKey($run->payroll_period_id)->lockForUpdate()->firstOrFail();

            if ($period->archived_at !== null || $period->deletionRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'period' => 'Periode sedang menunggu keputusan penghapusan atau sudah diarsipkan.',
                ]);
            }

            $locked = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== $expectedStatus) {
                throw ValidationException::withMessages([
                    'status' => 'Hasil payroll ini sudah diproses pihak lain, atau belum diajukan HRD.',
                ]);
            }

            if ($toStatus === 'approved') {
                (new PayrollReadiness)->assertReady($locked);
            }

            $locked->fill(array_merge(['status' => $toStatus], $extraAttributes))->save();

            PayrollApproval::create([
                'payroll_run_id' => $locked->id,
                'action' => $action,
                'from_status' => $expectedStatus,
                'to_status' => $toStatus,
                'actor_id' => $request->user()->id,
                'actor_snapshot' => ['name' => $request->user()->name],
                'note' => $note,
            ]);
        });
    }
}
