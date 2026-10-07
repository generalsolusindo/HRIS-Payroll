<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\PayrollApproval;
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
            ->whereHas('period', fn ($query) => $query->where('company_id', $request->user()->company_id))
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

        return Inertia::render('manager/payroll-run-approval', ['runs' => $runs]);
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

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    private function transition(Request $request, PayrollRun $run, string $expectedStatus, string $toStatus, string $action, ?string $note, array $extraAttributes): void
    {
        DB::transaction(function () use ($request, $run, $expectedStatus, $toStatus, $action, $note, $extraAttributes) {
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
