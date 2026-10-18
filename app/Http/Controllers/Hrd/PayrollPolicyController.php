<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\UpdatePayrollPolicyRequest;
use App\Models\PayrollPolicy;
use App\Services\PayrollAuditLogger;
use App\Services\PayrollMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPolicyController extends Controller
{
    use ScopesToOwnCompany;

    /**
     * Every company gets a "REGULAR" policy row (see PayrollPolicySeeder) —
     * this just guarantees one exists even for a company created after the
     * seeder ran, same firstOrCreate pattern PayrollRunController uses.
     * HRD only ever sees and edits their own company's policy — there is no
     * multi-company management role in this application.
     */
    public function index(Request $request): Response
    {
        $policy = PayrollPolicy::firstOrCreate(
            ['company_id' => $request->user()->company_id, 'code' => 'REGULAR', 'version' => 1],
            ['effective_from' => Carbon::today(), 'rules' => [], 'description' => 'Kebijakan awal — belum ada aturan potongan/reward.'],
        );

        $attendanceRules = $policy->rules['attendance'] ?? [];
        $overtimeRules = $policy->rules['overtime'] ?? [];

        return Inertia::render('hrd/attendance-policy', [
            'policy' => [
                'id' => $policy->id,
                'late_deduction_per_minute' => (string) ($attendanceRules['late_deduction_per_minute'] ?? '0'),
                'perfect_attendance_reward' => (string) ($attendanceRules['perfect_attendance_reward'] ?? '0'),
                'allowance_per_day' => (string) ($attendanceRules['allowance_per_day'] ?? '0'),
                'transport_per_day' => (string) ($attendanceRules['transport_per_day'] ?? '0'),
                'overtime_rate_per_hour' => (string) ($overtimeRules['rate_per_hour'] ?? '0'),
            ],
        ]);
    }

    /**
     * Edits the policy's rules in place rather than creating a new version
     * — there's no policy approval/versioning workflow yet (matches how
     * payroll runs themselves have no revision flow in this tahap either).
     * Past runs are unaffected: payroll_runs.policy_snapshot already
     * freezes the rules a run was calculated with at creation time.
     */
    public function update(UpdatePayrollPolicyRequest $request, PayrollPolicy $policy, PayrollAuditLogger $auditLogger): RedirectResponse
    {
        $this->assertOwnCompany($request, $policy->company_id);

        $validated = $request->validated();

        $before = $policy->rules;

        $after = [
            'attendance' => [
                'late_deduction_per_minute' => PayrollMoney::decimal((string) $validated['late_deduction_per_minute']),
                'perfect_attendance_reward' => PayrollMoney::decimal((string) $validated['perfect_attendance_reward']),
                'allowance_per_day' => PayrollMoney::decimal((string) $validated['allowance_per_day']),
                'transport_per_day' => PayrollMoney::decimal((string) $validated['transport_per_day']),
            ],
            'overtime' => [
                'rate_per_hour' => PayrollMoney::decimal((string) $validated['overtime_rate_per_hour']),
            ],
        ];

        $policy->update(['rules' => array_merge($policy->rules, $after)]);

        $auditLogger->log(
            companyId: $policy->company_id,
            entityType: 'PayrollPolicy',
            entityId: $policy->id,
            action: 'updated',
            before: $before,
            after: $after,
            reason: $validated['reason'] ?? 'Tarif kebijakan absensi/lembur diperbarui oleh HRD.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kebijakan absensi berhasil disimpan.']);

        return back();
    }
}
