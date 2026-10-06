<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\AttendanceReview;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollApproval;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AttendanceReviewGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    /**
     * Calculator: base salary + allowance (whatever earning components are
     * on each employee's profile) plus attendance-derived lines read from
     * attendance_reviews — late-minute and confirmed-absence deductions, and
     * a perfect-attendance reward, all rated by the company's PayrollPolicy
     * (see PayrollPolicyController). Still no overtime integration.
     *
     * A period can be (re)calculated when it has no run yet, or when its
     * latest version was sent back by a Manager ("revision_requested") —
     * that creates the next version (supersedes_run_id) and immediately
     * supersedes the old one, so exactly one version is ever active for a
     * period at a time. Any other status (submitted/approved/etc.) blocks
     * a silent recalculation.
     */
    public function store(Request $request, PayrollPeriod $period, AttendanceReviewGenerator $generator): RedirectResponse
    {
        $latestRun = $period->runs()->latest('version')->first();

        if ($latestRun && $latestRun->status !== 'revision_requested') {
            throw ValidationException::withMessages([
                'period' => 'Periode ini sudah punya hasil perhitungan. Hanya bisa dihitung ulang setelah Manager meminta revisi.',
            ]);
        }

        $policy = PayrollPolicy::firstOrCreate(
            ['company_id' => $period->company_id, 'code' => 'REGULAR', 'version' => 1],
            ['effective_from' => $period->starts_on, 'rules' => []],
        );

        // Refresh attendance_reviews for every date in the period's own
        // attendance window before reading from it, so the calculation
        // always reflects the latest clock-in/out and leave data — not
        // whatever happened to already exist from someone visiting the
        // Koreksi Absensi page. Dates in the future are skipped (nothing to
        // generate yet); a window that isn't fully elapsed will then simply
        // never qualify for the perfect-attendance reward below.
        $generationEnd = $period->attendance_ends_on->isFuture() ? today() : $period->attendance_ends_on;
        for ($date = $period->attendance_starts_on; $date->lte($generationEnd); $date = $date->addDay()) {
            $generator->generateForDate($date, $period->company_id);
        }

        $rules = $policy->rules['attendance'] ?? [];
        $lateRate = (float) ($rules['late_deduction_per_minute'] ?? 0);
        $absenceRate = (float) ($rules['absence_deduction_per_day'] ?? 0);
        $rewardRate = (float) ($rules['perfect_attendance_reward'] ?? 0);
        $expectedAttendanceDays = (int) $period->attendance_starts_on->diffInDays($period->attendance_ends_on) + 1;

        $attendanceComponents = $this->attendanceComponents($period->company_id);

        $result = DB::transaction(function () use ($period, $policy, $request, $lateRate, $absenceRate, $rewardRate, $expectedAttendanceDays, $attendanceComponents, $latestRun) {
            $run = PayrollRun::create([
                'payroll_period_id' => $period->id,
                'version' => $latestRun ? $latestRun->version + 1 : 1,
                'supersedes_run_id' => $latestRun?->id,
                'payroll_policy_id' => $policy->id,
                'policy_snapshot' => ['code' => $policy->code, 'version' => $policy->version, 'rules' => $policy->rules],
                'company_snapshot' => ['id' => $period->company_id, 'name' => $period->company->name],
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            if ($latestRun) {
                $latestRun->update(['status' => 'superseded']);
                PayrollApproval::create([
                    'payroll_run_id' => $latestRun->id,
                    'action' => 'superseded',
                    'from_status' => 'revision_requested',
                    'to_status' => 'superseded',
                    'actor_id' => $request->user()->id,
                    'actor_snapshot' => ['name' => $request->user()->name],
                    'note' => "Dihitung ulang sebagai versi {$run->version}.",
                ]);
            }

            $profiles = EmployeePayrollProfile::where('company_id', $period->company_id)
                ->whereHas('user', fn ($query) => $query->where('role', 'karyawan')->where('status', 'active'))
                ->with(['user', 'division', 'salaryComponents.payrollComponent'])
                ->get();

            $employeeCount = 0;
            $grossTotal = '0';
            $deductionTotal = '0';
            $rewardTotal = '0';
            $totalUnresolvedDays = 0;

            foreach ($profiles as $profile) {
                if ($profile->salaryComponents->isEmpty()) {
                    // No salary set up yet — skipped, not zeroed out, so it
                    // can't be mistaken for "this employee earns nothing".
                    continue;
                }

                $salaryGross = number_format((float) $profile->salaryComponents->sum('amount'), 2, '.', '');

                $reviews = AttendanceReview::where('user_id', $profile->user_id)
                    ->whereBetween('date', [$period->attendance_starts_on->toDateString(), $period->attendance_ends_on->toDateString()])
                    ->orderBy('date')
                    ->get();

                $attendance = $this->summarizeAttendance($reviews, $expectedAttendanceDays, $lateRate, $absenceRate, $rewardRate);
                $totalUnresolvedDays += $attendance['unresolved_days'];

                $gross = bcadd($salaryGross, $this->decimal($attendance['reward_amount']), 2);
                $deduction = bcadd($this->decimal($attendance['late_deduction_amount']), $this->decimal($attendance['absence_deduction_amount']), 2);
                $net = bcsub($gross, $deduction, 2);

                $entry = PayrollEntry::create([
                    'payroll_run_id' => $run->id,
                    'user_id' => $profile->user_id,
                    'employee_payroll_profile_id' => $profile->id,
                    'division_id' => $profile->division_id,
                    'employee_snapshot' => ['name' => $profile->user->name, 'email' => $profile->user->email],
                    'division_name' => $profile->division->name,
                    'position_name' => $profile->user->position->name,
                    'attendance_snapshot' => [
                        'window' => ['starts_on' => $period->attendance_starts_on->toDateString(), 'ends_on' => $period->attendance_ends_on->toDateString()],
                        'late_minutes_total' => $attendance['late_minutes_total'],
                        'absent_days' => $attendance['absent_days'],
                        'unresolved_days' => $attendance['unresolved_days'],
                        'perfect_attendance' => $attendance['perfect_attendance'],
                        'days' => $attendance['days'],
                    ],
                    'compensation_snapshot' => $profile->salaryComponents->map(fn (EmployeeSalaryComponent $component) => [
                        'component_code' => $component->payrollComponent->code,
                        'amount' => (string) $component->amount,
                        'basis' => $component->basis,
                    ])->all(),
                    'payment_destination' => $profile->bank_account_number ? [
                        'bank_name' => $profile->bank_name,
                        'account_number' => $profile->bank_account_number,
                        'account_holder' => $profile->bank_account_holder,
                    ] : null,
                    'gross_amount' => $gross,
                    'deduction_amount' => $deduction,
                    'reward_amount' => $attendance['reward_amount'] ?? '0',
                    'employer_contribution_amount' => '0',
                    'net_amount' => $net,
                ]);

                foreach ($profile->salaryComponents as $component) {
                    PayrollEntryLine::create([
                        'payroll_entry_id' => $entry->id,
                        'payroll_component_id' => $component->payroll_component_id,
                        'line_key' => 'salary-'.$component->payroll_component_id,
                        'component_code' => $component->payrollComponent->code,
                        'component_name' => $component->payrollComponent->name,
                        'type' => $component->payrollComponent->type,
                        'category' => $component->payrollComponent->category,
                        'quantity' => 1,
                        'rate' => $component->amount,
                        'amount' => $component->amount,
                        'source' => 'salary',
                        'calculation_snapshot' => ['basis' => $component->basis],
                    ]);
                }

                if ($attendance['late_deduction_amount'] !== null) {
                    PayrollEntryLine::create([
                        'payroll_entry_id' => $entry->id,
                        'payroll_component_id' => $attendanceComponents['late']->id,
                        'line_key' => 'attendance-late',
                        'component_code' => $attendanceComponents['late']->code,
                        'component_name' => $attendanceComponents['late']->name,
                        'type' => 'deduction',
                        'category' => 'attendance_deduction',
                        'quantity' => $attendance['late_minutes_total'],
                        'rate' => number_format($lateRate, 4, '.', ''),
                        'amount' => $attendance['late_deduction_amount'],
                        'source' => 'attendance',
                        'calculation_snapshot' => [
                            'late_minutes_total' => $attendance['late_minutes_total'],
                            'rate_per_minute' => $lateRate,
                            'policy_id' => $policy->id,
                            'policy_version' => $policy->version,
                        ],
                    ]);
                }

                if ($attendance['absence_deduction_amount'] !== null) {
                    PayrollEntryLine::create([
                        'payroll_entry_id' => $entry->id,
                        'payroll_component_id' => $attendanceComponents['absence']->id,
                        'line_key' => 'attendance-absence',
                        'component_code' => $attendanceComponents['absence']->code,
                        'component_name' => $attendanceComponents['absence']->name,
                        'type' => 'deduction',
                        'category' => 'attendance_deduction',
                        'quantity' => $attendance['absent_days'],
                        'rate' => number_format($absenceRate, 4, '.', ''),
                        'amount' => $attendance['absence_deduction_amount'],
                        'source' => 'attendance',
                        'calculation_snapshot' => [
                            'absent_days' => $attendance['absent_days'],
                            'rate_per_day' => $absenceRate,
                            'policy_id' => $policy->id,
                            'policy_version' => $policy->version,
                        ],
                    ]);
                }

                if ($attendance['reward_amount'] !== null) {
                    PayrollEntryLine::create([
                        'payroll_entry_id' => $entry->id,
                        'payroll_component_id' => $attendanceComponents['reward']->id,
                        'line_key' => 'attendance-perfect-reward',
                        'component_code' => $attendanceComponents['reward']->code,
                        'component_name' => $attendanceComponents['reward']->name,
                        'type' => 'earning',
                        'category' => 'attendance_reward',
                        'quantity' => 1,
                        'rate' => number_format($rewardRate, 4, '.', ''),
                        'amount' => $attendance['reward_amount'],
                        'source' => 'reward',
                        'calculation_snapshot' => [
                            'expected_attendance_days' => $expectedAttendanceDays,
                            'policy_id' => $policy->id,
                            'policy_version' => $policy->version,
                        ],
                    ]);
                }

                $employeeCount++;
                $grossTotal = bcadd($grossTotal, $gross, 2);
                $deductionTotal = bcadd($deductionTotal, $deduction, 2);
                $rewardTotal = bcadd($rewardTotal, $this->decimal($attendance['reward_amount']), 2);
            }

            $run->update([
                'employee_count' => $employeeCount,
                'gross_total' => $grossTotal,
                'deduction_total' => $deductionTotal,
                'reward_total' => $rewardTotal,
                'net_total' => bcsub($grossTotal, $deductionTotal, 2),
            ]);

            return ['run' => $run, 'unresolved_days_total' => $totalUnresolvedDays];
        });

        $run = $result['run'];
        $unresolvedDaysTotal = $result['unresolved_days_total'];

        $skipped = EmployeePayrollProfile::where('company_id', $period->company_id)
            ->whereHas('user', fn ($query) => $query->where('role', 'karyawan')->where('status', 'active'))
            ->whereDoesntHave('salaryComponents')
            ->count();
        $withoutProfile = User::where('company_id', $period->company_id)
            ->where('role', 'karyawan')->where('status', 'active')
            ->whereDoesntHave('payrollProfiles')
            ->count();
        $totalSkipped = $skipped + $withoutProfile;

        $message = "Payroll dihitung untuk {$run->employee_count} karyawan.";
        if ($totalSkipped > 0) {
            $message .= " {$totalSkipped} karyawan dilewati karena belum punya profil gaji.";
        }
        if ($unresolvedDaysTotal > 0) {
            $message .= " {$unresolvedDaysTotal} hari absensi masih perlu ditinjau di Koreksi Absensi — belum dihitung sebagai potongan maupun reward.";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('hrd.hasil-payroll.show', $run);
    }

    /**
     * Sends a draft run to the Manager for approval — the only transition
     * HRD itself can make without a Manager decision.
     */
    public function submit(Request $request, PayrollRun $run): RedirectResponse
    {
        DB::transaction(function () use ($request, $run) {
            $locked = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya hasil perhitungan berstatus draft yang bisa diajukan.',
                ]);
            }

            $locked->fill(['status' => 'submitted', 'submitted_at' => now()])->save();

            PayrollApproval::create([
                'payroll_run_id' => $locked->id,
                'action' => 'submitted',
                'from_status' => 'draft',
                'to_status' => 'submitted',
                'actor_id' => $request->user()->id,
                'actor_snapshot' => ['name' => $request->user()->name],
                'note' => null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hasil payroll diajukan untuk persetujuan Manager.']);

        return back();
    }

    public function show(PayrollRun $run): Response
    {
        $run->load('period.company', 'entries.division', 'entries.lines', 'entries.payslips', 'approvals.actor');

        $canPublishSlips = in_array($run->status, ['approved', 'locked', 'partially_paid', 'paid'], true);

        return Inertia::render('hrd/payroll-run-show', [
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
                'user_id' => $entry->user_id,
                'name' => $entry->employee_snapshot['name'] ?? '—',
                'division_name' => $entry->division_name,
                'position_name' => $entry->position_name,
                'gross_amount' => (string) $entry->gross_amount,
                'deduction_amount' => (string) $entry->deduction_amount,
                'reward_amount' => (string) $entry->reward_amount,
                'net_amount' => (string) $entry->net_amount,
                'unresolved_days' => $entry->attendance_snapshot['unresolved_days'] ?? 0,
                'lines' => $entry->lines->map(fn (PayrollEntryLine $line) => [
                    'component_name' => $line->component_name,
                    'type' => $line->type,
                    'amount' => (string) $line->amount,
                ]),
                'can_publish_slip' => $canPublishSlips,
                'latest_payslip' => $this->latestPayslip($entry),
            ]),
        ]);
    }

    /**
     * @return array{id: int, number: string, version: int, published_at: string|null}|null
     */
    private function latestPayslip(PayrollEntry $entry): ?array
    {
        $payslip = $entry->payslips->where('status', 'published')->sortByDesc('version')->first();

        if (! $payslip) {
            return null;
        }

        return [
            'id' => $payslip->id,
            'number' => $payslip->number,
            'version' => $payslip->version,
            'published_at' => $payslip->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{late: PayrollComponent, absence: PayrollComponent, reward: PayrollComponent}
     */
    private function attendanceComponents(int $companyId): array
    {
        return [
            'late' => PayrollComponent::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'ATTENDANCE_LATE'],
                ['name' => 'Potongan Keterlambatan', 'type' => 'deduction', 'category' => 'attendance_deduction', 'is_active' => true],
            ),
            'absence' => PayrollComponent::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'ATTENDANCE_ABSENCE'],
                ['name' => 'Potongan Alpha', 'type' => 'deduction', 'category' => 'attendance_deduction', 'is_active' => true],
            ),
            'reward' => PayrollComponent::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'ATTENDANCE_PERFECT'],
                ['name' => 'Reward Kehadiran Sempurna', 'type' => 'earning', 'category' => 'attendance_reward', 'is_active' => true],
            ),
        ];
    }

    /**
     * Aggregates a period's attendance_reviews into deduction/reward
     * amounts. "unresolved" rows (and any date with no row at all, e.g. a
     * window not yet fully elapsed) never contribute a deduction — only an
     * explicit "present" row's late_minutes or an explicit "absent"
     * correction counts. The perfect-attendance reward additionally
     * requires every single day in the window to have been reviewed, so it
     * can never be paid on incomplete data.
     *
     * @param  Collection<int, AttendanceReview>  $reviews
     * @return array{
     *     late_minutes_total: int, absent_days: int, unresolved_days: int,
     *     perfect_attendance: bool, late_deduction_amount: string|null,
     *     absence_deduction_amount: string|null, reward_amount: string|null,
     *     days: array<int, array<string, mixed>>,
     * }
     */
    private function summarizeAttendance(Collection $reviews, int $expectedAttendanceDays, float $lateRate, float $absenceRate, float $rewardRate): array
    {
        $lateMinutesTotal = 0;
        $absentDays = 0;
        $unresolvedDays = 0;
        $days = [];

        foreach ($reviews as $review) {
            if ($review->classification === 'present') {
                $lateMinutesTotal += $review->late_minutes;
            } elseif ($review->classification === 'absent') {
                $absentDays++;
            } elseif ($review->classification === 'unresolved') {
                $unresolvedDays++;
            }

            $days[] = [
                'date' => $review->date->toDateString(),
                'attendance_review_id' => $review->id,
                'attendance_review_version' => $review->version,
                'classification' => $review->classification,
                'late_minutes' => $review->late_minutes,
                'worked_minutes' => $review->worked_minutes,
                'scheduled_minutes' => $review->scheduled_minutes,
                'schedule_snapshot' => $review->schedule_snapshot,
                'leave_snapshot' => $review->leave_snapshot,
            ];
        }

        $perfectAttendance = $reviews->count() === $expectedAttendanceDays
            && $lateMinutesTotal === 0 && $absentDays === 0 && $unresolvedDays === 0;

        return [
            'late_minutes_total' => $lateMinutesTotal,
            'absent_days' => $absentDays,
            'unresolved_days' => $unresolvedDays,
            'perfect_attendance' => $perfectAttendance,
            'late_deduction_amount' => ($lateMinutesTotal > 0 && $lateRate > 0)
                ? number_format($lateMinutesTotal * $lateRate, 2, '.', '') : null,
            'absence_deduction_amount' => ($absentDays > 0 && $absenceRate > 0)
                ? number_format($absentDays * $absenceRate, 2, '.', '') : null,
            'reward_amount' => ($perfectAttendance && $rewardRate > 0)
                ? number_format($rewardRate, 2, '.', '') : null,
            'days' => $days,
        ];
    }

    /**
     * @return numeric-string
     */
    private function decimal(?string $value): string
    {
        return number_format((float) ($value ?? '0'), 2, '.', '');
    }
}
