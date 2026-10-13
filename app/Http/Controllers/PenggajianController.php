<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollPayslip;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PenggajianController extends Controller
{
    /**
     * An employee's own published payslips — HRD sees everyone's via the
     * payroll run screen instead, so this stays scoped to the viewer.
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

        return Inertia::render('penggajian', ['payslips' => $payslips]);
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
}
