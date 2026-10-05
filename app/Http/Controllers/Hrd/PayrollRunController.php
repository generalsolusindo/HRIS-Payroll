<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    /**
     * First calculator slice: base salary + allowance only (whatever
     * earning components are on each employee's profile) — net is just
     * gross for now. No deductions, reward, overtime, or attendance
     * integration yet; no approval workflow either, every run is created
     * straight into 'draft'.
     */
    public function store(Request $request, PayrollPeriod $period): RedirectResponse
    {
        if ($period->runs()->exists()) {
            throw ValidationException::withMessages([
                'period' => 'Periode ini sudah punya hasil perhitungan. Revisi belum didukung di tahap ini.',
            ]);
        }

        $policy = PayrollPolicy::firstOrCreate(
            ['company_id' => $period->company_id, 'code' => 'REGULAR', 'version' => 1],
            ['effective_from' => $period->starts_on, 'rules' => []],
        );

        $run = DB::transaction(function () use ($period, $policy, $request) {
            $run = PayrollRun::create([
                'payroll_period_id' => $period->id,
                'version' => 1,
                'payroll_policy_id' => $policy->id,
                'policy_snapshot' => ['code' => $policy->code, 'version' => $policy->version, 'rules' => $policy->rules],
                'company_snapshot' => ['id' => $period->company_id, 'name' => $period->company->name],
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $profiles = EmployeePayrollProfile::where('company_id', $period->company_id)
                ->whereHas('user', fn ($query) => $query->where('role', 'karyawan')->where('status', 'active'))
                ->with(['user', 'division', 'salaryComponents.payrollComponent'])
                ->get();

            $employeeCount = 0;
            $grossTotal = '0';

            foreach ($profiles as $profile) {
                if ($profile->salaryComponents->isEmpty()) {
                    // No salary set up yet — skipped, not zeroed out, so it
                    // can't be mistaken for "this employee earns nothing".
                    continue;
                }

                $gross = number_format((float) $profile->salaryComponents->sum('amount'), 2, '.', '');

                $entry = PayrollEntry::create([
                    'payroll_run_id' => $run->id,
                    'user_id' => $profile->user_id,
                    'employee_payroll_profile_id' => $profile->id,
                    'division_id' => $profile->division_id,
                    'employee_snapshot' => ['name' => $profile->user->name, 'email' => $profile->user->email],
                    'division_name' => $profile->division->name,
                    'position_name' => $profile->user->position->name,
                    'attendance_snapshot' => [],
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
                    'deduction_amount' => '0',
                    'reward_amount' => '0',
                    'employer_contribution_amount' => '0',
                    'net_amount' => $gross,
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

                $employeeCount++;
                $grossTotal = bcadd($grossTotal, $gross, 2);
            }

            $run->update([
                'employee_count' => $employeeCount,
                'gross_total' => $grossTotal,
                'net_total' => $grossTotal,
            ]);

            return $run;
        });

        $skipped = EmployeePayrollProfile::where('company_id', $period->company_id)
            ->whereHas('user', fn ($query) => $query->where('role', 'karyawan')->where('status', 'active'))
            ->whereDoesntHave('salaryComponents')
            ->count();
        $withoutProfile = User::where('company_id', $period->company_id)
            ->where('role', 'karyawan')->where('status', 'active')
            ->whereDoesntHave('payrollProfiles')
            ->count();
        $totalSkipped = $skipped + $withoutProfile;

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $totalSkipped > 0
                ? "Payroll dihitung untuk {$run->employee_count} karyawan. {$totalSkipped} karyawan dilewati karena belum punya profil gaji."
                : "Payroll dihitung untuk {$run->employee_count} karyawan.",
        ]);

        return redirect()->route('hrd.hasil-payroll.show', $run);
    }

    public function show(PayrollRun $run): Response
    {
        $run->load('period.company', 'entries.division', 'entries.lines');

        return Inertia::render('hrd/payroll-run-show', [
            'run' => [
                'id' => $run->id,
                'version' => $run->version,
                'status' => $run->status,
                'employee_count' => $run->employee_count,
                'gross_total' => (string) $run->gross_total,
                'net_total' => (string) $run->net_total,
                'period' => [
                    'name' => $run->period->name,
                    'company_name' => $run->period->company->name,
                    'starts_on' => $run->period->starts_on->toDateString(),
                    'ends_on' => $run->period->ends_on->toDateString(),
                ],
            ],
            'entries' => $run->entries->map(fn (PayrollEntry $entry) => [
                'id' => $entry->id,
                'user_id' => $entry->user_id,
                'name' => $entry->employee_snapshot['name'] ?? '—',
                'division_name' => $entry->division_name,
                'position_name' => $entry->position_name,
                'gross_amount' => (string) $entry->gross_amount,
                'deduction_amount' => (string) $entry->deduction_amount,
                'net_amount' => (string) $entry->net_amount,
                'lines' => $entry->lines->map(fn (PayrollEntryLine $line) => [
                    'component_name' => $line->component_name,
                    'type' => $line->type,
                    'amount' => (string) $line->amount,
                ]),
            ]),
        ]);
    }
}
