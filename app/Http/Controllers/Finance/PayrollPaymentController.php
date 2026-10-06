<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePayrollPaymentRequest;
use App\Models\PayrollApproval;
use App\Models\PayrollEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPaymentController extends Controller
{
    /**
     * Runs Finance can act on or needs to keep an eye on — anything from
     * "approved" (ready to lock) through "paid" (done, kept for history).
     * Draft/submitted/revision_requested runs never appear here: there is
     * nothing for Finance to do until a Manager has approved a version.
     */
    public function index(): Response
    {
        $runs = PayrollRun::whereIn('status', ['approved', 'locked', 'partially_paid', 'paid'])
            ->with('period.company')
            ->latest('approved_at')
            ->get()
            ->map(fn (PayrollRun $run) => [
                'id' => $run->id,
                'version' => $run->version,
                'status' => $run->status,
                'employee_count' => $run->employee_count,
                'net_total' => (string) $run->net_total,
                'paid_total' => number_format($this->paidTotal($run), 2, '.', ''),
                'period' => [
                    'name' => $run->period->name,
                    'company_name' => $run->period->company->name,
                    'starts_on' => $run->period->starts_on->toDateString(),
                    'ends_on' => $run->period->ends_on->toDateString(),
                ],
            ]);

        return Inertia::render('finance/payroll-payments', ['runs' => $runs]);
    }

    public function show(PayrollRun $run): Response
    {
        abort_unless(in_array($run->status, ['approved', 'locked', 'partially_paid', 'paid'], true), 404);

        $run->load('period.company', 'entries.division', 'entries.payments');

        return Inertia::render('finance/payroll-run-show', [
            'run' => [
                'id' => $run->id,
                'version' => $run->version,
                'status' => $run->status,
                'employee_count' => $run->employee_count,
                'net_total' => (string) $run->net_total,
                'period' => [
                    'name' => $run->period->name,
                    'company_name' => $run->period->company->name,
                    'starts_on' => $run->period->starts_on->toDateString(),
                    'ends_on' => $run->period->ends_on->toDateString(),
                ],
            ],
            'entries' => $run->entries->map(function (PayrollEntry $entry) {
                $paid = $this->confirmedTotal($entry);

                return [
                    'id' => $entry->id,
                    'name' => $entry->employee_snapshot['name'] ?? '—',
                    'division_name' => $entry->division_name,
                    'net_amount' => (string) $entry->net_amount,
                    'paid_amount' => number_format($paid, 2, '.', ''),
                    'remaining_amount' => number_format(max(0, (float) $entry->net_amount - $paid), 2, '.', ''),
                    'has_destination' => $entry->payment_destination !== null,
                    'payments' => $entry->payments->sortByDesc('created_at')->values()->map(fn (PayrollPayment $payment) => [
                        'id' => $payment->id,
                        'amount' => (string) $payment->amount,
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'reference' => $payment->reference,
                        'has_proof' => $payment->proof_path !== null,
                        'note' => $payment->note,
                        'created_at' => $payment->created_at->toIso8601String(),
                    ]),
                ];
            }),
        ]);
    }

    public function lock(Request $request, PayrollRun $run): RedirectResponse
    {
        DB::transaction(function () use ($request, $run) {
            $locked = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya hasil payroll berstatus disetujui yang bisa dikunci.',
                ]);
            }

            $locked->update(['status' => 'locked']);

            PayrollApproval::create([
                'payroll_run_id' => $locked->id,
                'action' => 'locked',
                'from_status' => 'approved',
                'to_status' => 'locked',
                'actor_id' => $request->user()->id,
                'actor_snapshot' => ['name' => $request->user()->name],
                'note' => null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hasil payroll dikunci — siap diproses pembayarannya.']);

        return back();
    }

    public function storePayment(StorePayrollPaymentRequest $request, PayrollEntry $entry): RedirectResponse
    {
        $run = $entry->run;
        abort_unless(in_array($run->status, ['locked', 'partially_paid'], true), 422, 'Kunci hasil payroll ini sebelum mencatat pembayaran.');

        $validated = $request->validated();
        $remaining = (float) $entry->net_amount - $this->confirmedTotal($entry);

        if ((float) $validated['amount'] > $remaining + 0.004) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal melebihi sisa yang belum dibayar ('.number_format($remaining, 0, ',', '.').').',
            ]);
        }

        DB::transaction(function () use ($request, $entry, $validated) {
            $proofPath = null;
            if ($request->hasFile('proof')) {
                $proofPath = $request->file('proof')->store((string) $entry->id, 'payment_proofs');
            }

            PayrollPayment::create([
                'payroll_entry_id' => $entry->id,
                'idempotency_key' => (string) Str::uuid(),
                'amount' => number_format((float) $validated['amount'], 2, '.', ''),
                'method' => $validated['method'],
                'status' => 'confirmed',
                'reference' => $validated['reference'] ?? null,
                'proof_path' => $proofPath,
                'destination_snapshot' => $entry->payment_destination,
                'paid_at' => now(),
                'recorded_by' => $request->user()->id,
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
                'note' => $validated['note'] ?? null,
            ]);

            $this->recomputeRunStatus($entry->run()->lockForUpdate()->first());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran dicatat.']);

        return back();
    }

    public function voidPayment(Request $request, PayrollPayment $payment): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($payment, $validated) {
            if ($payment->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pembayaran terkonfirmasi yang bisa dibatalkan.',
                ]);
            }

            $payment->update(['status' => 'voided', 'note' => $validated['note']]);

            $this->recomputeRunStatus($payment->entry->run()->lockForUpdate()->first());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran dibatalkan.']);

        return back();
    }

    private function confirmedTotal(PayrollEntry $entry): float
    {
        return (float) $entry->payments()->where('status', 'confirmed')->sum('amount');
    }

    private function paidTotal(PayrollRun $run): float
    {
        return (float) PayrollPayment::where('status', 'confirmed')
            ->whereIn('payroll_entry_id', $run->entries()->pluck('id'))
            ->sum('amount');
    }

    private function recomputeRunStatus(PayrollRun $run): void
    {
        $entries = $run->entries;
        $fullyPaid = 0;
        $anyPaid = false;

        foreach ($entries as $entry) {
            $paid = $this->confirmedTotal($entry);
            if ($paid > 0) {
                $anyPaid = true;
            }
            if ($paid + 0.004 >= (float) $entry->net_amount) {
                $fullyPaid++;
            }
        }

        $status = match (true) {
            $entries->isNotEmpty() && $fullyPaid === $entries->count() => 'paid',
            $anyPaid => 'partially_paid',
            default => 'locked',
        };

        $run->update(['status' => $status]);
    }
}
