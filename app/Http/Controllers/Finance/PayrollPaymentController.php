<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePayrollPaymentRequest;
use App\Models\PayrollApproval;
use App\Models\PayrollEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPaymentController extends Controller
{
    use ScopesToOwnCompany;

    /**
     * Runs Finance can act on or needs to keep an eye on — anything from
     * "approved" (ready to lock) through "paid" (done, kept for history).
     * Draft/submitted/revision_requested runs never appear here: there is
     * nothing for Finance to do until a Manager has approved a version.
     */
    public function index(Request $request): Response
    {
        $runs = PayrollRun::whereIn('status', ['approved', 'locked', 'partially_paid', 'paid'])
            ->whereHas('period', fn ($query) => $query->where('company_id', $request->user()->company_id))
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

    public function show(Request $request, PayrollRun $run): Response
    {
        abort_unless(in_array($run->status, ['approved', 'locked', 'partially_paid', 'paid'], true), 404);
        $this->assertOwnCompany($request, $run->period->company_id);

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
        $this->assertOwnCompany($request, $run->period->company_id);

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
        $this->assertOwnCompany($request, $entry->run->period->company_id);

        $validated = $request->validated();

        // Uploading before the transaction so a slow disk write never holds
        // the row lock below open — the path is only ever persisted if the
        // transaction commits.
        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store((string) $entry->id, 'payment_proofs');
        }

        try {
            DB::transaction(function () use ($request, $entry, $validated, $proofPath) {
                // Locking the entry (not just the run) serializes every
                // concurrent payment attempt against THIS entry specifically:
                // a second request blocks here until the first commits, so
                // the balance it reads next already reflects the first
                // payment — closing the TOCTOU window that previously let
                // two concurrent requests both read the same "remaining"
                // amount and both insert, overpaying the entry.
                $lockedEntry = PayrollEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
                $run = $lockedEntry->run()->lockForUpdate()->first();

                abort_unless(in_array($run->status, ['locked', 'partially_paid'], true), 422, 'Kunci hasil payroll ini sebelum mencatat pembayaran.');

                $remaining = (float) $lockedEntry->net_amount - $this->confirmedTotal($lockedEntry);
                if ((float) $validated['amount'] > $remaining + 0.004) {
                    throw ValidationException::withMessages([
                        'amount' => 'Nominal melebihi sisa yang belum dibayar ('.number_format($remaining, 0, ',', '.').').',
                    ]);
                }

                PayrollPayment::create([
                    'payroll_entry_id' => $lockedEntry->id,
                    'idempotency_key' => $validated['idempotency_key'],
                    'amount' => number_format((float) $validated['amount'], 2, '.', ''),
                    'method' => $validated['method'],
                    'status' => 'confirmed',
                    'reference' => $validated['reference'] ?? null,
                    'proof_path' => $proofPath,
                    'destination_snapshot' => $lockedEntry->payment_destination,
                    'paid_at' => now(),
                    'recorded_by' => $request->user()->id,
                    'confirmed_by' => $request->user()->id,
                    'confirmed_at' => now(),
                    'note' => $validated['note'] ?? null,
                ]);

                $this->recomputeRunStatus($run);
            });
        } catch (UniqueConstraintViolationException) {
            // idempotency_key is unique — a client retrying an identical
            // submission (e.g. after a dropped response) lands here instead
            // of recording a second payment. Not an error from the user's
            // point of view: the payment was already recorded.
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran sudah tercatat sebelumnya.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran dicatat.']);

        return back();
    }

    public function voidPayment(Request $request, PayrollPayment $payment): RedirectResponse
    {
        $this->assertOwnCompany($request, $payment->entry->run->period->company_id);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($payment, $validated) {
            // Same reasoning as storePayment: lock the entry first so a
            // void can't race a concurrent payment attempt on it.
            $lockedPayment = PayrollPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            PayrollEntry::whereKey($lockedPayment->payroll_entry_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pembayaran terkonfirmasi yang bisa dibatalkan.',
                ]);
            }

            $lockedPayment->update(['status' => 'voided', 'note' => $validated['note']]);

            $this->recomputeRunStatus($lockedPayment->entry->run()->lockForUpdate()->first());
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
