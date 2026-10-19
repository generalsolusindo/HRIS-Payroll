<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollEntryComplaint;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPayslip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PenggajianController extends Controller
{
    /**
     * An employee's own published payslips, plus any draft run HRD has
     * opened up for pre-approval review ("Pratinjau Gaji" — see
     * PayrollRunController::share()). HRD sees everyone's via the payroll
     * run screen instead, so this stays scoped to the viewer.
     */
    public function index(Request $request): Response
    {
        $payslips = PayrollPayslip::where('status', 'published')
            ->whereIn('payroll_entry_id', PayrollEntry::where('user_id', $request->user()->id)->pluck('id'))
            ->with('entry.run.period')
            ->latest('published_at')
            ->get()
            ->map(fn (PayrollPayslip $payslip) => [
                'id' => $payslip->id,
                'number' => $payslip->number,
                'period_name' => $payslip->entry->run->period->name,
                'net_amount' => (string) $payslip->entry->net_amount,
                'published_at' => $payslip->published_at?->toIso8601String(),
            ]);

        $previews = PayrollEntry::where('user_id', $request->user()->id)
            ->whereHas('run', fn ($query) => $query->whereNotNull('shared_at'))
            ->with('run.period')
            ->latest('id')
            ->get()
            ->map(fn (PayrollEntry $entry) => [
                'id' => $entry->id,
                'period_name' => $entry->run->period->name,
                'net_amount' => (string) $entry->net_amount,
                'run_status' => $entry->run->status,
                'shared_at' => $entry->run->shared_at->toIso8601String(),
            ]);

        return Inertia::render('penggajian', ['payslips' => $payslips, 'previews' => $previews]);
    }

    public function show(Request $request, PayrollPayslip $payslip): Response
    {
        $payslip->loadMissing('entry.user');

        // Role middleware only checks the "hrd" role name, not the
        // tenant boundary — an HRD must only reach this self-service
        // route (not the dedicated /hrd/hasil-payroll screen) for a
        // payslip belonging to their own company.
        abort_unless(
            $payslip->entry->user_id === $request->user()->id
                || ($request->user()->role === 'hrd' && $payslip->entry->user->company_id === $request->user()->company_id),
            403,
        );

        return Inertia::render('penggajian-detail', [
            'payslip' => [
                'id' => $payslip->id,
                'number' => $payslip->number,
                'version' => $payslip->version,
                'status' => $payslip->status,
                'published_at' => $payslip->published_at?->toIso8601String(),
                'snapshot' => $payslip->snapshot,
            ],
        ]);
    }

    /**
     * Live (never frozen) view of the employee's own entry in a run HRD
     * has shared for pre-approval review — deliberately not a Payslip
     * snapshot, since the whole point is that these figures can still
     * change before HRD submits to the Manager.
     */
    public function pratinjau(Request $request, PayrollEntry $entry): Response
    {
        $entry->loadMissing('run.period', 'lines', 'complaints');

        abort_unless(
            $entry->user_id === $request->user()->id && $entry->run->shared_at !== null,
            403,
        );

        return Inertia::render('penggajian-pratinjau', [
            'preview' => [
                'id' => $entry->id,
                'run_status' => $entry->run->status,
                'shared_at' => $entry->run->shared_at->toIso8601String(),
                'period' => [
                    'name' => $entry->run->period->name,
                    'starts_on' => $entry->run->period->starts_on->toDateString(),
                    'ends_on' => $entry->run->period->ends_on->toDateString(),
                ],
                'division_name' => $entry->division_name,
                'position_name' => $entry->position_name,
                'gross_amount' => (string) $entry->gross_amount,
                'deduction_amount' => (string) $entry->deduction_amount,
                'reward_amount' => (string) $entry->reward_amount,
                'net_amount' => (string) $entry->net_amount,
                'attendance_summary' => [
                    'late_minutes_total' => $entry->attendance_snapshot['late_minutes_total'] ?? 0,
                    'absent_days' => $entry->attendance_snapshot['absent_days'] ?? 0,
                    'perfect_attendance' => $entry->attendance_snapshot['perfect_attendance'] ?? false,
                ],
                'lines' => $entry->lines->map(fn (PayrollEntryLine $line) => [
                    'component_name' => $line->component_name,
                    'type' => $line->type,
                    'amount' => (string) $line->amount,
                ])->all(),
                'complaints' => $entry->complaints->sortBy('id')->values()->map(fn (PayrollEntryComplaint $complaint) => [
                    'id' => $complaint->id,
                    'message' => $complaint->message,
                    'status' => $complaint->status,
                    'hrd_note' => $complaint->hrd_note,
                    'created_at' => $complaint->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * A free-text complaint/adjustment request against the employee's own
     * preview entry — never changes any figure by itself. It's purely a
     * note for HRD to act on manually (e.g. via Tambah Penyesuaian Manual),
     * same as the "report outside the system" path this replaces, just
     * captured inside the app instead of over chat/Telegram.
     */
    public function storeComplaint(Request $request, PayrollEntry $entry): RedirectResponse
    {
        abort_unless(
            $entry->user_id === $request->user()->id && $entry->run->shared_at !== null,
            403,
        );

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        PayrollEntryComplaint::create([
            'payroll_entry_id' => $entry->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'status' => 'new',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Komplain/penyesuaian terkirim ke HRD.']);

        return back();
    }
}
