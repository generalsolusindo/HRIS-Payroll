<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\UpdatePayrollPolicyRequest;
use App\Models\Company;
use App\Models\PayrollPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPolicyController extends Controller
{
    /**
     * Every company gets a "REGULAR" policy row (see PayrollPolicySeeder) —
     * this just guarantees one exists even for a company created after the
     * seeder ran, same firstOrCreate pattern PayrollRunController uses.
     */
    public function index(): Response
    {
        $policies = Company::orderBy('name')->get()->map(function (Company $company) {
            $policy = PayrollPolicy::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'REGULAR', 'version' => 1],
                ['effective_from' => Carbon::today(), 'rules' => [], 'description' => 'Kebijakan awal — belum ada aturan potongan/reward.'],
            );

            $attendanceRules = $policy->rules['attendance'] ?? [];
            $overtimeRules = $policy->rules['overtime'] ?? [];

            return [
                'id' => $policy->id,
                'company' => ['id' => $company->id, 'name' => $company->name],
                'late_deduction_per_minute' => (string) ($attendanceRules['late_deduction_per_minute'] ?? '0'),
                'absence_deduction_per_day' => (string) ($attendanceRules['absence_deduction_per_day'] ?? '0'),
                'perfect_attendance_reward' => (string) ($attendanceRules['perfect_attendance_reward'] ?? '0'),
                'overtime_rate_per_hour' => (string) ($overtimeRules['rate_per_hour'] ?? '0'),
            ];
        });

        return Inertia::render('hrd/attendance-policy', ['policies' => $policies]);
    }

    /**
     * Edits the policy's rules in place rather than creating a new version
     * — there's no policy approval/versioning workflow yet (matches how
     * payroll runs themselves have no revision flow in this tahap either).
     * Past runs are unaffected: payroll_runs.policy_snapshot already
     * freezes the rules a run was calculated with at creation time.
     */
    public function update(UpdatePayrollPolicyRequest $request, PayrollPolicy $policy): RedirectResponse
    {
        $validated = $request->validated();

        $policy->update([
            'rules' => array_merge($policy->rules, [
                'attendance' => [
                    'late_deduction_per_minute' => number_format((float) $validated['late_deduction_per_minute'], 2, '.', ''),
                    'absence_deduction_per_day' => number_format((float) $validated['absence_deduction_per_day'], 2, '.', ''),
                    'perfect_attendance_reward' => number_format((float) $validated['perfect_attendance_reward'], 2, '.', ''),
                ],
                'overtime' => [
                    'rate_per_hour' => number_format((float) $validated['overtime_rate_per_hour'], 2, '.', ''),
                ],
            ]),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kebijakan absensi berhasil disimpan.']);

        return back();
    }
}
