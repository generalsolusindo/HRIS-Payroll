<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPayslip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollPayslipController extends Controller
{
    use ScopesToOwnCompany;

    /**
     * Publishes a new payslip version for one employee's entry, snapshotting
     * the numbers as they stand right now. Only possible once a Manager has
     * approved the run — before that the figures can still change. A prior
     * published slip for the same entry is superseded, never edited in
     * place, so a previously shared/printed slip is never silently altered.
     */
    public function store(Request $request, PayrollEntry $entry): RedirectResponse
    {
        $entry->loadMissing('run.period', 'lines');

        $this->assertOwnCompany($request, $entry->run->period->company_id);

        abort_unless(
            in_array($entry->run->status, ['approved', 'locked', 'partially_paid', 'paid'], true),
            422,
            'Hasil payroll ini belum disetujui Manager — slip belum bisa diterbitkan.',
        );

        DB::transaction(function () use ($request, $entry) {
            $nextVersion = (int) PayrollPayslip::where('payroll_entry_id', $entry->id)->max('version') + 1;

            PayrollPayslip::where('payroll_entry_id', $entry->id)
                ->where('status', 'published')
                ->update(['status' => 'superseded']);

            PayrollPayslip::create([
                'payroll_entry_id' => $entry->id,
                'number' => "SLIP-{$entry->id}-{$nextVersion}",
                'version' => $nextVersion,
                'status' => 'published',
                'snapshot' => [
                    'employee' => $entry->employee_snapshot,
                    'division_name' => $entry->division_name,
                    'position_name' => $entry->position_name,
                    'period' => [
                        'name' => $entry->run->period->name,
                        'starts_on' => $entry->run->period->starts_on->toDateString(),
                        'ends_on' => $entry->run->period->ends_on->toDateString(),
                    ],
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
                ],
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Slip gaji diterbitkan.']);

        return back();
    }
}
