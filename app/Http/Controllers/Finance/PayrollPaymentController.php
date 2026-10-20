<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePayrollPaymentRequest;
use App\Models\PayrollApproval;
use App\Models\PayrollEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\NavBadgeService;
use App\Services\PayrollAuditLogger;
use App\Services\PayrollMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    public function index(Request $request, NavBadgeService $navBadges): Response
    {
        $navBadges->markSeen($request->user(), 'finance_payroll');

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
                'paid_total' => $this->paidTotal($run),
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

        $run->load('period.company', 'entries.division', 'entries.payments', 'entries.user', 'approvals.actor');

        return Inertia::render('finance/payroll-run-show', [
            'approvals' => $run->approvals->sortBy('created_at')->values()->map(fn (PayrollApproval $approval) => [
                'id' => $approval->id,
                'action' => $approval->action,
                'actor_name' => $approval->actor !== null ? $approval->actor->name : ($approval->actor_snapshot['name'] ?? 'Sistem'),
                'note' => $approval->note,
                'created_at' => $approval->created_at->toIso8601String(),
            ]),
            'run' => [
                'id' => $run->id,
                'version' => $run->version,
                'status' => $run->status,
                'employee_count' => $run->employee_count,
                'net_total' => (string) $run->net_total,
                'paid_total' => $this->paidTotal($run),
                'period' => [
                    'name' => $run->period->name,
                    'company_name' => $run->period->company->name,
                    'starts_on' => $run->period->starts_on->toDateString(),
                    'ends_on' => $run->period->ends_on->toDateString(),
                ],
            ],
            'entries' => $run->entries->map(function (PayrollEntry $entry) {
                $paid = $this->confirmedTotal($entry);
                $destination = $this->currentDestination($entry);

                return [
                    'id' => $entry->id,
                    'name' => $entry->employee_snapshot['name'] ?? '—',
                    'division_name' => $entry->division_name,
                    'net_amount' => (string) $entry->net_amount,
                    'paid_amount' => $paid,
                    'remaining_amount' => bccomp($entry->net_amount, $paid, 2) > 0 ? bcsub($entry->net_amount, $paid, 2) : '0.00',
                    'has_destination' => $destination !== null,
                    'destination' => $destination,
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

            $locked->update(['status' => 'locked', 'locked_at' => now()]);

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

        abort_if($proofPath === false, 500, 'Gagal menyimpan bukti pembayaran.');
        $stored = false;
        try {
            $stored = DB::transaction(function () use ($request, $entry, $validated, $proofPath) {
                // All payroll mutations acquire run -> entry -> payment locks.
                $run = PayrollRun::whereKey($entry->payroll_run_id)->lockForUpdate()->firstOrFail();
                $lockedEntry = PayrollEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
                $amount = PayrollMoney::decimal((string) $validated['amount']);
                $existing = PayrollPayment::where('idempotency_key', $validated['idempotency_key'])->first();
                if ($existing) {
                    if ($existing->payroll_entry_id !== $entry->id || bccomp(PayrollMoney::decimal($existing->amount), $amount, 2) !== 0
                        || $existing->method !== $validated['method'] || $existing->reference !== ($validated['reference'] ?? null)) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Kode permintaan sudah dipakai untuk pembayaran berbeda.']);
                    }

                    return false; // Also handles retry after full payment or voiding.
                }
                if (! in_array($run->status, ['locked', 'partially_paid'], true)) {
                    throw ValidationException::withMessages(['status' => 'Kunci hasil payroll sebelum mencatat pembayaran.']);
                }
                $remaining = bcsub($lockedEntry->net_amount, $this->confirmedTotal($lockedEntry), 2);
                if (bccomp($amount, $remaining, 2) > 0) {
                    throw ValidationException::withMessages(['amount' => 'Nominal melebihi sisa yang belum dibayar (Rp '.$remaining.').']);
                }

                $payment = PayrollPayment::create([
                    'payroll_entry_id' => $lockedEntry->id,
                    'idempotency_key' => $validated['idempotency_key'],
                    'amount' => $amount,
                    'method' => $validated['method'],
                    'status' => 'confirmed',
                    'reference' => $validated['reference'] ?? null,
                    'proof_path' => $proofPath,
                    'destination_snapshot' => $this->currentDestination($lockedEntry),
                    'paid_at' => now(),
                    'recorded_by' => $request->user()->id,
                    'confirmed_by' => $request->user()->id,
                    'confirmed_at' => now(),
                    'note' => $validated['note'] ?? null,
                ]);

                $this->recomputeRunStatus($run);
                (new PayrollAuditLogger)->log($run->period->company_id, 'PayrollPayment', $payment->id, 'payment_recorded', null,
                    ['amount' => $amount, 'method' => $payment->method, 'status' => 'confirmed'], $validated['note'] ?? 'Pembayaran dicatat Finance.', $run->id);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kode permintaan pembayaran sudah digunakan. Muat ulang data pembayaran.']);
        } finally {
            if (! $stored && is_string($proofPath)) {
                Storage::disk('payment_proofs')->delete($proofPath);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $stored ? 'Pembayaran dicatat.' : 'Permintaan ini sudah tercatat; tidak dibuat pembayaran baru.']);

        return back();
    }

    public function voidPayment(Request $request, PayrollPayment $payment): RedirectResponse
    {
        $this->assertOwnCompany($request, $payment->entry->run->period->company_id);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($payment, $validated) {
            $run = PayrollRun::whereKey($payment->entry->payroll_run_id)->lockForUpdate()->firstOrFail();
            PayrollEntry::whereKey($payment->payroll_entry_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = PayrollPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pembayaran terkonfirmasi yang bisa dibatalkan.',
                ]);
            }

            $before = $lockedPayment->only(['amount', 'status', 'note']);
            $lockedPayment->update(['status' => 'voided', 'note' => $validated['note']]);

            $this->recomputeRunStatus($run);
            (new PayrollAuditLogger)->log($run->period->company_id, 'PayrollPayment', $lockedPayment->id, 'payment_voided', $before, ['status' => 'voided'], $validated['note'], $run->id);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran dibatalkan.']);

        return back();
    }

    /**
     * Reads bank details live off the employee's User record rather than
     * the payment_destination column frozen on the entry at run-generation
     * time — HRD routinely fills in a missing bank account in Manajemen
     * User only after the run already exists, and that update must be
     * reflected before Finance records a payment.
     *
     * @return array{bank_name: ?string, account_number: ?string, account_holder: ?string}|null
     */
    private function currentDestination(PayrollEntry $entry): ?array
    {
        $user = $entry->user instanceof User ? $entry->user : $entry->user()->first();

        if ($user === null || ! $user->bank_account_number) {
            return null;
        }

        return [
            'bank_name' => $user->bank_name,
            'account_number' => $user->bank_account_number,
            'account_holder' => $user->bank_account_holder,
        ];
    }

    /** @return numeric-string */
    private function confirmedTotal(PayrollEntry $entry): string
    {
        return $entry->payments()->where('status', 'confirmed')->get()->reduce(
            fn (string $total, PayrollPayment $payment) => bcadd($total, PayrollMoney::decimal($payment->amount), 2), '0.00');
    }

    /** @return numeric-string */
    private function paidTotal(PayrollRun $run): string
    {
        return PayrollPayment::where('status', 'confirmed')->whereIn('payroll_entry_id', $run->entries()->select('id'))->get()->reduce(
            fn (string $total, PayrollPayment $payment) => bcadd($total, PayrollMoney::decimal($payment->amount), 2), '0.00');
    }

    private function recomputeRunStatus(PayrollRun $run): void
    {
        $entries = $run->entries()->get();
        $fullyPaid = 0;
        $anyPaid = false;

        foreach ($entries as $entry) {
            $paid = $this->confirmedTotal($entry);
            if (bccomp($paid, '0', 2) > 0) {
                $anyPaid = true;
            }
            if (bccomp($paid, $entry->net_amount, 2) >= 0) {
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
