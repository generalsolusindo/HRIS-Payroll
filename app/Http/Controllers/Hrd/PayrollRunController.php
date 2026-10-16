<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\AttendanceReview;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\PayrollApproval;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AttendanceReviewGenerator;
use App\Services\PayrollMoney;
use App\Services\PayrollReadiness;
use App\Services\PayrollTotals;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    use ScopesToOwnCompany;

    /**
     * Calculator: base salary + allowance (whatever earning components are
     * on each employee's profile) plus attendance-derived lines read from
     * attendance_reviews — late-minute and confirmed-absence deductions, and
     * a perfect-attendance reward — plus an overtime earning line from
     * approved overtime_requests, all rated by the company's PayrollPolicy
     * (see PayrollPolicyController).
     *
     * A period can be (re)calculated when it has no run yet, or when its
     * latest version was sent back by a Manager ("revision_requested") —
     * that creates the next version (supersedes_run_id) and immediately
     * supersedes the old one, so exactly one version is ever active for a
     * period at a time. Any other status (submitted/approved/etc.) blocks
     * a silent recalculation.
     */
    public function store(Request $request, PayrollPeriod $period, AttendanceReviewGenerator $generator, bool $refreshDraft = false): RedirectResponse
    {
        $this->assertOwnCompany($request, $period->company_id);

        $latestRun = $period->runs()->latest('version')->first();

        // A cancelled run (see cancel()) is treated the same as
        // revision_requested here — it also frees the period up for a
        // brand new calculation, it just got there by HRD cancelling
        // instead of Manager sending it back.
        $recalculableStatuses = ['revision_requested', 'cancelled'];
        if (($refreshDraft && (! $latestRun || $latestRun->status !== 'draft')) || (! $refreshDraft && $latestRun && ! in_array($latestRun->status, $recalculableStatuses, true))) {
            throw ValidationException::withMessages([
                'period' => 'Periode ini sudah punya hasil perhitungan. Hanya bisa dihitung ulang setelah Manager meminta revisi atau setelah dibatalkan.',
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
        $lateRate = PayrollMoney::decimal((string) ($rules['late_deduction_per_minute'] ?? '0'));
        $absenceRate = PayrollMoney::decimal((string) ($rules['absence_deduction_per_day'] ?? '0'));
        $rewardRate = PayrollMoney::decimal((string) ($rules['perfect_attendance_reward'] ?? '0'));
        $overtimeRate = PayrollMoney::decimal((string) ($policy->rules['overtime']['rate_per_hour'] ?? '0'));
        $expectedAttendanceDays = (int) $period->attendance_starts_on->diffInDays($period->attendance_ends_on) + 1;

        $attendanceComponents = $this->attendanceComponents($period->company_id);
        $overtimeComponent = $this->overtimeComponent($period->company_id);
        $allowanceComponent = $this->allowanceComponent($period->company_id);

        $result = DB::transaction(function () use ($period, $policy, $request, $lateRate, $absenceRate, $rewardRate, $overtimeRate, $expectedAttendanceDays, $attendanceComponents, $overtimeComponent, $allowanceComponent, $latestRun, $refreshDraft, $recalculableStatuses) {
            PayrollPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $currentLatest = $period->runs()->latest('version')->first();
            $currentLatestOk = $refreshDraft ? $currentLatest?->status === 'draft' : (! $currentLatest || in_array($currentLatest->status, $recalculableStatuses, true));
            if ($currentLatest?->id !== $latestRun?->id || ! $currentLatestOk) {
                throw ValidationException::withMessages(['period' => 'Periode telah diproses pengguna lain. Muat ulang halaman.']);
            }
            if ($latestRun) {
                $latestRun = PayrollRun::whereKey($latestRun->id)->lockForUpdate()->firstOrFail();
                $latestRunOk = $refreshDraft ? $latestRun->status === 'draft' : in_array($latestRun->status, $recalculableStatuses, true);
                if (! $latestRunOk) {
                    throw ValidationException::withMessages(['period' => 'Status payroll telah berubah. Muat ulang halaman.']);
                }
            }
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

            // A cancelled run keeps its 'cancelled' status forever (and the
            // reason it was cancelled stays legible in the approval trail)
            // — it must not get relabeled 'superseded', which means
            // something different (a normal "sent back for revision" cycle).
            if ($latestRun && $latestRun->status !== 'cancelled') {
                $fromStatus = $latestRun->status;
                $latestRun->update(['status' => 'superseded']);
                PayrollApproval::create([
                    'payroll_run_id' => $latestRun->id,
                    'action' => 'superseded',
                    'from_status' => $fromStatus,
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

                foreach ($profile->salaryComponents as $component) {
                    if (! in_array($component->payrollComponent->type, ['earning', 'deduction'], true) || $component->payrollComponent->company_id !== $period->company_id) {
                        throw ValidationException::withMessages(['period' => 'Profil gaji mengandung komponen bukan pendapatan/potongan atau milik perusahaan lain. Perbaiki profil sebelum menghitung.']);
                    }
                }

                $salaryGross = $profile->salaryComponents
                    ->filter(fn (EmployeeSalaryComponent $component) => $component->payrollComponent->type === 'earning')
                    ->reduce(fn (string $sum, EmployeeSalaryComponent $component) => bcadd($sum, PayrollMoney::decimal($component->amount), 2), '0.00');

                $salaryManualDeduction = $profile->salaryComponents
                    ->filter(fn (EmployeeSalaryComponent $component) => $component->payrollComponent->type === 'deduction')
                    ->reduce(fn (string $sum, EmployeeSalaryComponent $component) => bcadd($sum, PayrollMoney::decimal($component->amount), 2), '0.00');

                $reviews = AttendanceReview::where('user_id', $profile->user_id)
                    ->whereBetween('date', [$period->attendance_starts_on->toDateString(), $period->attendance_ends_on->toDateString()])
                    ->orderBy('date')
                    ->get();

                $allowanceRate = PayrollMoney::decimal((string) $profile->division->daily_attendance_allowance);
                $attendance = $this->summarizeAttendance($reviews, $expectedAttendanceDays, $lateRate, $absenceRate, $rewardRate, $allowanceRate);
                $totalUnresolvedDays += $attendance['unresolved_days'];

                $overtimeMinutes = (int) OvertimeRequest::where('user_id', $profile->user_id)
                    ->where('status', 'approved')
                    ->whereBetween('starts_at', [$period->attendance_starts_on->startOfDay(), $period->attendance_ends_on->endOfDay()])
                    ->sum('approved_minutes');
                $overtimeAmount = ($overtimeMinutes > 0 && $overtimeRate > 0)
                    ? PayrollMoney::multiply($overtimeRate, $overtimeMinutes, 60) : null;

                $gross = bcadd($salaryGross, $this->decimal($attendance['reward_amount']), 2);
                $gross = bcadd($gross, $this->decimal($overtimeAmount), 2);
                $gross = bcadd($gross, $this->decimal($attendance['allowance_amount']), 2);
                $deduction = bcadd($this->decimal($attendance['late_deduction_amount']), $this->decimal($attendance['absence_deduction_amount']), 2);
                $deduction = bcadd($deduction, $salaryManualDeduction, 2);
                $net = bcsub($gross, $deduction, 2);

                $entry = PayrollEntry::create([
                    'payroll_run_id' => $run->id,
                    'user_id' => $profile->user_id,
                    'employee_payroll_profile_id' => $profile->id,
                    'division_id' => $profile->division_id,
                    'employee_snapshot' => ['name' => $profile->user->name, 'email' => $profile->user->email],
                    'division_name' => $profile->division->name,
                    'position_name' => $profile->user->division?->name ?? '-',
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
                    'payment_destination' => $profile->user->bank_account_number ? [
                        'bank_name' => $profile->user->bank_name,
                        'account_number' => $profile->user->bank_account_number,
                        'account_holder' => $profile->user->bank_account_holder,
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
                        'quantity' => $attendance['late_minutes_total'],
                        'rate' => PayrollMoney::decimal($lateRate, 4),
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
                        'quantity' => $attendance['absent_days'],
                        'rate' => PayrollMoney::decimal($absenceRate, 4),
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
                        'quantity' => 1,
                        'rate' => PayrollMoney::decimal($rewardRate, 4),
                        'amount' => $attendance['reward_amount'],
                        'source' => 'reward',
                        'calculation_snapshot' => [
                            'expected_attendance_days' => $expectedAttendanceDays,
                            'policy_id' => $policy->id,
                            'policy_version' => $policy->version,
                        ],
                    ]);
                }

                if ($overtimeAmount !== null) {
                    PayrollEntryLine::create([
                        'payroll_entry_id' => $entry->id,
                        'payroll_component_id' => $overtimeComponent->id,
                        'line_key' => 'overtime',
                        'component_code' => $overtimeComponent->code,
                        'component_name' => $overtimeComponent->name,
                        'type' => 'earning',
                        'quantity' => PayrollMoney::decimal(bcdiv((string) $overtimeMinutes, '60', 8), 4),
                        'rate' => PayrollMoney::decimal($overtimeRate, 4),
                        'amount' => $overtimeAmount,
                        'source' => 'overtime',
                        'calculation_snapshot' => [
                            'approved_minutes' => $overtimeMinutes,
                            'rate_per_hour' => $overtimeRate,
                            'policy_id' => $policy->id,
                            'policy_version' => $policy->version,
                        ],
                    ]);
                }

                if ($attendance['allowance_amount'] !== null) {
                    PayrollEntryLine::create([
                        'payroll_entry_id' => $entry->id,
                        'payroll_component_id' => $allowanceComponent->id,
                        'line_key' => 'attendance-allowance',
                        'component_code' => $allowanceComponent->code,
                        'component_name' => $allowanceComponent->name,
                        'type' => 'earning',
                        'quantity' => PayrollMoney::decimal(bcadd((string) $attendance['present_days'], bcdiv((string) $attendance['half_days'], '2', 4), 4), 4),
                        'rate' => PayrollMoney::decimal($allowanceRate, 4),
                        'amount' => $attendance['allowance_amount'],
                        'source' => 'allowance',
                        'calculation_snapshot' => [
                            'present_days' => $attendance['present_days'],
                            'half_days' => $attendance['half_days'],
                            'rate_per_day' => $allowanceRate,
                            'division_id' => $profile->division_id,
                        ],
                    ]);
                }

                // Carry manual decisions into the revised run; never silently discard them.
                if ($latestRun) {
                    $priorEntry = $latestRun->entries()->where('user_id', $entry->user_id)->first();
                    foreach ($priorEntry?->lines()->where('source', 'manual')->get() ?? [] as $priorLine) {
                        $copy = $priorLine->replicate();
                        $copy->payroll_entry_id = $entry->id;
                        $copy->calculation_snapshot = [...$priorLine->calculation_snapshot, 'copied_from_line_id' => $priorLine->id];
                        $copy->save();
                    }
                    (new PayrollTotals)->refreshEntry($entry);
                    $gross = PayrollMoney::decimal($entry->gross_amount);
                    $deduction = PayrollMoney::decimal($entry->deduction_amount);
                    if (bccomp($entry->net_amount, '0', 2) < 0) {
                        throw ValidationException::withMessages(['period' => 'Revisi menghasilkan gaji bersih negatif. Periksa profil, tarif, dan penyesuaian manual.']);
                    }
                }

                $employeeCount++;
                $grossTotal = bcadd($grossTotal, $gross, 2);
                $deductionTotal = bcadd($deductionTotal, $deduction, 2);
                $rewardTotal = bcadd($rewardTotal, $this->decimal($attendance['reward_amount']), 2);
            }

            if ($latestRun && $latestRun->entries()
                ->whereNotIn('user_id', $run->entries()->select('user_id'))
                ->whereHas('lines', fn ($query) => $query->where('source', 'manual')->where('amount', '>', 0))
                ->exists()) {
                throw ValidationException::withMessages(['period' => 'Karyawan dengan penyesuaian manual tidak masuk perhitungan baru. Periksa profil/status karyawan agar penyesuaian tidak hilang.']);
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
    public function refresh(Request $request, PayrollPeriod $period, AttendanceReviewGenerator $generator): RedirectResponse
    {
        return $this->store($request, $period, $generator, true);
    }

    public function submit(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->assertOwnCompany($request, $run->period->company_id);

        DB::transaction(function () use ($request, $run) {
            $locked = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya hasil perhitungan berstatus draft yang bisa diajukan.',
                ]);
            }

            (new PayrollReadiness)->assertReady($locked);

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

    /**
     * Soft-cancel — never a hard delete. The run row, its entries/lines,
     * payslips, and the full approval trail all stay exactly as they were;
     * only the status flips to 'cancelled' and a mandatory reason is
     * recorded, the same pattern PayrollAdjustmentController already uses
     * for cancelling a single manual adjustment line. Allowed up through
     * 'locked' (approved, not yet paid) — once Finance has actually
     * recorded a payment (partially_paid/paid), the money has already
     * moved and cancelling the run here would leave that unexplained.
     */
    public function cancel(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->assertOwnCompany($request, $run->period->company_id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $run, $validated) {
            $locked = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['draft', 'submitted', 'revision_requested', 'approved', 'locked'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hasil payroll ini sudah ada pembayaran tercatat, atau sudah dibatalkan — tidak bisa dibatalkan lagi.',
                ]);
            }

            $fromStatus = $locked->status;
            $locked->update(['status' => 'cancelled']);

            PayrollApproval::create([
                'payroll_run_id' => $locked->id,
                'action' => 'cancelled',
                'from_status' => $fromStatus,
                'to_status' => 'cancelled',
                'actor_id' => $request->user()->id,
                'actor_snapshot' => ['name' => $request->user()->name],
                'note' => $validated['reason'],
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hasil payroll dibatalkan. Periode ini bisa dihitung ulang dari awal kalau diperlukan.']);

        return back();
    }

    /**
     * Opens a still-draft run's figures up for the employees in it to
     * review on their own "Penggajian" page, before HRD submits it to the
     * Manager — the point is to catch a mistake (wrong attendance, missing
     * component) while it can still be fixed, not after the numbers are
     * already on their way up the approval chain. Deliberately opt-in
     * (HRD reviews it first, then shares) rather than automatic the
     * moment "Hitung Payroll" runs. Once shared it stays shared — the
     * employee keeps seeing their live entry figures through the rest of
     * the run's lifecycle, there is no "unshare".
     */
    public function share(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->assertOwnCompany($request, $run->period->company_id);

        DB::transaction(function () use ($request, $run) {
            $locked = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya hasil payroll berstatus draft yang bisa dibagikan untuk ditinjau karyawan.',
                ]);
            }
            if ($locked->shared_at !== null) {
                return;
            }

            $locked->update(['shared_at' => now()]);

            PayrollApproval::create([
                'payroll_run_id' => $locked->id,
                'action' => 'shared_for_review',
                'from_status' => 'draft',
                'to_status' => 'draft',
                'actor_id' => $request->user()->id,
                'actor_snapshot' => ['name' => $request->user()->name],
                'note' => null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft dibagikan — karyawan sekarang bisa meninjau angka gajinya sebelum diajukan ke Manager.']);

        return back();
    }

    public function show(Request $request, PayrollRun $run): Response
    {
        $run->load('period.company', 'entries.division', 'entries.lines', 'entries.payslips', 'approvals.actor');

        $this->assertOwnCompany($request, $run->period->company_id);

        $canPublishSlips = in_array($run->status, ['approved', 'locked', 'partially_paid', 'paid'], true);

        $isHrd = $request->user()->role === 'hrd';

        return Inertia::render('hrd/payroll-run-show', [
            'readiness_issues' => (new PayrollReadiness)->issues($run),
            'capabilities' => [
                'submit' => $isHrd && $run->status === 'draft',
                'recalculate' => $isHrd && in_array($run->status, ['revision_requested', 'cancelled'], true),
                'adjust' => $isHrd && $run->status === 'draft',
                'refresh' => $isHrd && $run->status === 'draft',
                'cancel' => $isHrd && in_array($run->status, ['draft', 'submitted', 'revision_requested', 'approved', 'locked'], true),
                'share' => $isHrd && $run->status === 'draft' && $run->shared_at === null,
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
                'shared_at' => $run->shared_at?->toIso8601String(),
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
                'user_id' => $entry->user_id,
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
                    'quantity' => (string) $line->quantity,
                    'rate' => (string) $line->rate,
                    'adjustment_reason' => $line->adjustment_reason,
                    'cancelled' => isset($line->calculation_snapshot['cancelled_at']),
                    'cancellation_reason' => $line->calculation_snapshot['cancellation_reason'] ?? null,
                    'component_name' => $line->component_name,
                    'type' => $line->type,
                    'amount' => (string) $line->amount,
                ]),
                'can_publish_slip' => $isHrd && $canPublishSlips,
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
                ['name' => 'Potongan Keterlambatan', 'type' => 'deduction', 'is_active' => true],
            ),
            'absence' => PayrollComponent::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'ATTENDANCE_ABSENCE'],
                ['name' => 'Potongan Alpha', 'type' => 'deduction', 'is_active' => true],
            ),
            'reward' => PayrollComponent::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'ATTENDANCE_PERFECT'],
                ['name' => 'Reward Kehadiran Sempurna', 'type' => 'earning', 'is_active' => true],
            ),
        ];
    }

    private function overtimeComponent(int $companyId): PayrollComponent
    {
        return PayrollComponent::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'OVERTIME'],
            ['name' => 'Lembur', 'type' => 'earning', 'is_active' => true],
        );
    }

    private function allowanceComponent(int $companyId): PayrollComponent
    {
        return PayrollComponent::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'ATTENDANCE_ALLOWANCE'],
            ['name' => 'Tunjangan Kehadiran', 'type' => 'earning', 'is_active' => true],
        );
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
     *     late_minutes_total: int, present_days: int, half_days: int,
     *     absent_days: int, unresolved_days: int,
     *     perfect_attendance: bool, late_deduction_amount: string|null,
     *     absence_deduction_amount: string|null, reward_amount: string|null,
     *     allowance_amount: string|null,
     *     days: array<int, array<string, mixed>>,
     * }
     */
    private function summarizeAttendance(Collection $reviews, int $expectedAttendanceDays, string $lateRate, string $absenceRate, string $rewardRate, string $allowanceRate): array
    {
        $lateMinutesTotal = 0;
        $presentDays = 0;
        $halfDays = 0;
        $absentDays = 0;
        $unresolvedDays = 0;
        $days = [];

        foreach ($reviews as $review) {
            if ($review->classification === 'present') {
                $lateMinutesTotal += $review->late_minutes;
                $presentDays++;
            } elseif ($review->classification === 'half_day') {
                $lateMinutesTotal += $review->late_minutes;
                $halfDays++;
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
            && $lateMinutesTotal === 0 && $halfDays === 0 && $absentDays === 0 && $unresolvedDays === 0;

        $allowanceAmount = null;
        if (($presentDays > 0 || $halfDays > 0) && $allowanceRate > 0) {
            $allowanceAmount = bcadd(
                PayrollMoney::multiply($allowanceRate, $presentDays),
                PayrollMoney::multiply($allowanceRate, $halfDays, 2),
                2,
            );
        }

        return [
            'late_minutes_total' => $lateMinutesTotal,
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'unresolved_days' => $unresolvedDays,
            'perfect_attendance' => $perfectAttendance,
            'late_deduction_amount' => ($lateMinutesTotal > 0 && $lateRate > 0)
                ? PayrollMoney::multiply($lateRate, $lateMinutesTotal) : null,
            'absence_deduction_amount' => ($absentDays > 0 && $absenceRate > 0)
                ? PayrollMoney::multiply($absenceRate, $absentDays) : null,
            'reward_amount' => ($perfectAttendance && $rewardRate > 0)
                ? PayrollMoney::decimal($rewardRate) : null,
            'allowance_amount' => $allowanceAmount,
            'days' => $days,
        ];
    }

    /**
     * @return numeric-string
     */
    private function decimal(?string $value): string
    {
        return PayrollMoney::decimal($value ?? '0');
    }
}
