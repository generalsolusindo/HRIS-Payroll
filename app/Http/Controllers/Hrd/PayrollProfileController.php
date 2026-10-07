<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\UpdatePayrollProfileRequest;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\User;
use App\Services\PayrollAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollProfileController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        $employees = User::where('company_id', $request->user()->company_id)
            ->where('role', 'karyawan')
            ->where('status', 'active')
            ->with([
                'position:id,name',
                'payrollProfiles' => fn ($query) => $query->latest('effective_from')->limit(1)->with('division:id,name', 'salaryComponents.payrollComponent:id,name'),
            ])
            ->orderBy('name')
            ->get(['id', 'position_id', 'name', 'email'])
            ->map(function (User $user) {
                $profile = $user->payrollProfiles->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => ['id' => $user->position->id, 'name' => $user->position->name],
                    'has_profile' => $profile !== null,
                    'division_name' => $profile?->division?->name,
                    'monthly_total' => $profile?->salaryComponents->sum('amount'),
                ];
            });

        return Inertia::render('hrd/payroll-profiles', ['employees' => $employees]);
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless($user->role === 'karyawan', 404);
        $this->assertOwnCompany($request, $user->company_id);

        $profile = EmployeePayrollProfile::where('user_id', $user->id)
            ->latest('effective_from')
            ->with('salaryComponents')
            ->first();

        return Inertia::render('hrd/payroll-profile-edit', [
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'company_id' => $user->company_id,
                'company_name' => $user->company->name,
            ],
            'profile' => $profile ? [
                'division_id' => $profile->division_id,
                'effective_from' => $profile->effective_from->toDateString(),
                'employment_started_on' => $profile->employment_started_on->toDateString(),
                'employment_type' => $profile->employment_type,
                'bank_name' => $profile->bank_name,
                'bank_account_number' => $profile->bank_account_number,
                'bank_account_holder' => $profile->bank_account_holder,
                'components' => $profile->salaryComponents->map(fn (EmployeeSalaryComponent $component) => [
                    'payroll_component_id' => $component->payroll_component_id,
                    'amount' => (string) $component->amount,
                ]),
            ] : null,
            'divisions' => Division::where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'availableComponents' => PayrollComponent::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where('type', 'earning')
                ->orderBy('name')
                ->get(['id', 'name', 'category']),
        ]);
    }

    public function update(UpdatePayrollProfileRequest $request, User $user, PayrollAuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($user->role === 'karyawan', 404);
        $this->assertOwnCompany($request, $user->company_id);

        $validated = $request->validated();

        $existingProfile = EmployeePayrollProfile::where('user_id', $user->id)->with('salaryComponents')->first();
        // Deliberately excludes bank_account_number/bank_account_holder —
        // those are encrypted at rest specifically to protect them, and
        // the audit log's before/after columns are plain JSON, not
        // encrypted. The salary amounts below are the part that actually
        // matters for this trail.
        $before = $existingProfile ? [
            'division_id' => $existingProfile->division_id,
            'employment_type' => $existingProfile->employment_type,
            'effective_from' => $existingProfile->effective_from->toDateString(),
            'bank_name' => $existingProfile->bank_name,
            'components' => $existingProfile->salaryComponents->map(fn (EmployeeSalaryComponent $component) => [
                'payroll_component_id' => $component->payroll_component_id,
                'amount' => (string) $component->amount,
            ])->all(),
        ] : null;

        $profile = DB::transaction(function () use ($validated, $user) {
            // One current profile per employee for now — history-aware
            // versioning (multiple effective-dated profiles) lands once the
            // payroll run calculator actually needs to snapshot changes
            // mid-period, not before.
            $profile = EmployeePayrollProfile::firstOrNew(['user_id' => $user->id]);
            $profile->fill([
                'company_id' => $user->company_id,
                'division_id' => $validated['division_id'],
                'position_id' => $user->position_id,
                'effective_from' => $validated['effective_from'],
                'employment_started_on' => $validated['employment_started_on'],
                'employment_type' => $validated['employment_type'],
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_account_holder' => $validated['bank_account_holder'] ?? null,
            ]);
            $profile->save();

            $submittedComponentIds = collect((array) $validated['components'])->pluck('payroll_component_id');
            $profile->salaryComponents()->whereNotIn('payroll_component_id', $submittedComponentIds)->delete();

            foreach ($validated['components'] as $component) {
                EmployeeSalaryComponent::updateOrCreate(
                    [
                        'employee_payroll_profile_id' => $profile->id,
                        'payroll_component_id' => $component['payroll_component_id'],
                    ],
                    ['amount' => $component['amount'], 'basis' => 'monthly'],
                );
            }

            return $profile;
        });

        $auditLogger->log(
            companyId: $user->company_id,
            entityType: 'EmployeePayrollProfile',
            entityId: $profile->id,
            action: $existingProfile ? 'updated' : 'created',
            before: $before,
            after: [
                'division_id' => $profile->division_id,
                'employment_type' => $profile->employment_type,
                'effective_from' => $profile->effective_from->toDateString(),
                'bank_name' => $profile->bank_name,
                'components' => $validated['components'],
            ],
            reason: $validated['reason'] ?? "Profil gaji {$user->name} diperbarui oleh HRD.",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Profil gaji {$user->name} berhasil disimpan."]);

        return redirect()->route('hrd.profil-gaji.index');
    }
}
