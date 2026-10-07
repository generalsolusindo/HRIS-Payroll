<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollRun;
use App\Services\PayrollAuditLogger;
use App\Services\PayrollMoney;
use App\Services\PayrollTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PayrollAdjustmentController extends Controller
{
    use ScopesToOwnCompany;

    public function store(Request $request, PayrollEntry $entry, PayrollTotals $totals, PayrollAuditLogger $audit): RedirectResponse
    {
        $this->assertOwnCompany($request, $entry->run->period->company_id);
        $data = $request->validate([
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
            'request_id' => ['required', 'uuid'],
        ]);
        $amount = PayrollMoney::decimal((string) $data['amount']);
        $hash = hash('sha256', json_encode([$data['type'], $data['name'], $amount, $data['reason']], JSON_THROW_ON_ERROR));

        DB::transaction(function () use ($request, $entry, $data, $amount, $hash, $totals, $audit) {
            $run = PayrollRun::whereKey($entry->payroll_run_id)->lockForUpdate()->firstOrFail();
            $lockedEntry = PayrollEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($run);
            $key = 'manual-'.$data['request_id'];
            $existing = $lockedEntry->lines()->where('line_key', $key)->first();
            if ($existing) {
                if (($existing->calculation_snapshot['request_hash'] ?? null) !== $hash) {
                    throw ValidationException::withMessages(['amount' => 'Permintaan ini sudah dipakai untuk penyesuaian berbeda. Buka formulir baru.']);
                }

                return; // Retry, including a cancelled adjustment: never recreate it.
            }
            $component = PayrollComponent::firstOrCreate(
                ['company_id' => $run->period->company_id, 'code' => 'MANUAL_'.strtoupper($data['type'])],
                ['name' => 'Penyesuaian manual', 'type' => $data['type'], 'category' => 'other', 'is_active' => true],
            );
            $before = $lockedEntry->only(['gross_amount', 'deduction_amount', 'net_amount']);
            $line = $lockedEntry->lines()->create([
                'payroll_component_id' => $component->id, 'line_key' => $key,
                'component_code' => $component->code, 'component_name' => $data['name'],
                'type' => $data['type'], 'category' => 'other', 'source' => 'manual',
                'quantity' => '1', 'rate' => $amount, 'amount' => $amount,
                'adjustment_reason' => $data['reason'], 'created_by' => $request->user()->id,
                'calculation_snapshot' => ['request_hash' => $hash, 'original_amount' => $amount],
            ]);
            $totals->refreshEntry($lockedEntry);
            $this->assertNonNegative($lockedEntry);
            $totals->refreshRun($run);
            $audit->log($run->period->company_id, 'PayrollEntryLine', $line->id, 'manual_added', $before,
                ['line' => $line->only(['component_name', 'type', 'amount']), 'totals' => $lockedEntry->only(['gross_amount', 'deduction_amount', 'net_amount'])],
                $data['reason'], $run->id);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penyesuaian tersimpan dan total diperbarui.']);

        return back();
    }

    public function destroy(Request $request, PayrollEntryLine $line, PayrollTotals $totals, PayrollAuditLogger $audit): RedirectResponse
    {
        $this->assertOwnCompany($request, $line->entry->run->period->company_id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $line, $data, $totals, $audit) {
            $run = PayrollRun::whereKey($line->entry->payroll_run_id)->lockForUpdate()->firstOrFail();
            $entry = PayrollEntry::whereKey($line->payroll_entry_id)->lockForUpdate()->firstOrFail();
            $locked = PayrollEntryLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($run);
            abort_unless($locked->source === 'manual', 422, 'Hanya penyesuaian manual yang dapat dibatalkan.');
            if (isset($locked->calculation_snapshot['cancelled_at'])) {
                return;
            }
            $before = $locked->only(['amount', 'component_name', 'type', 'adjustment_reason']);
            $locked->update(['amount' => '0.00', 'rate' => '0.00', 'calculation_snapshot' => [
                ...$locked->calculation_snapshot, 'cancelled_at' => now()->toIso8601String(),
                'cancelled_by' => $request->user()->id, 'cancellation_reason' => $data['reason'],
            ]]);
            $totals->refreshEntry($entry);
            $this->assertNonNegative($entry);
            $totals->refreshRun($run);
            $audit->log($run->period->company_id, 'PayrollEntryLine', $locked->id, 'manual_cancelled', $before, ['amount' => '0.00'], $data['reason'], $run->id);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penyesuaian dibatalkan; jejak perubahan tetap tersimpan.']);

        return back();
    }

    private function assertDraft(PayrollRun $run): void
    {
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Penyesuaian hanya dapat dilakukan pada payroll draft.']);
        }
    }

    private function assertNonNegative(PayrollEntry $entry): void
    {
        if (bccomp($entry->net_amount, '0', 2) < 0) {
            throw ValidationException::withMessages(['amount' => 'Penyesuaian tidak dapat membuat gaji bersih negatif.']);
        }
    }
}
