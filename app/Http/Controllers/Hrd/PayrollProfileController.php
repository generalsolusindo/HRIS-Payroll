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
                'division:id,name',
                'payrollProfiles' => fn ($query) => $query->latest('effective_from')->limit(1)->with('division:id,name', 'salaryComponents.payrollComponent:id,name,type'),
            ])
            ->orderBy('name')
            ->get(['id', 'division_id', 'name', 'email'])
            ->map(function (User $user) {
                $profile = $user->payrollProfiles->first();

                // Net of the manually-set components only (earning minus
                // deduction) — a preview of the monthly base, not the
                // actual computed net pay (which also factors in
                // attendance/overtime from the payroll run).
                $monthlyTotal = $profile?->salaryComponents->sum(
                    fn (EmployeeSalaryComponent $component) => $component->payrollComponent->type === 'deduction'
                        ? -$component->amount : $component->amount
                );

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->division ? ['id' => $user->division->id, 'name' => $user->division->name] : null,
                    'has_profile' => $profile !== null,
                    'division_name' => $profile?->division?->name,
                    'monthly_total' => $monthlyTotal,
                ];
            });

        return Inertia::render('hrd/payroll-profiles', ['employees' => $employees]);
    }

    public function edit(Request $request, User $user): Response|RedirectResponse
    {
        abort_unless($user->role === 'karyawan', 404);
        $this->assertOwnCompany($request, $user->company_id);

        // employment_started_on, division_id, and employment_type now live
        // on the user (set once at Manajemen User) and flow into the
        // profile automatically — HRD must set them there first rather
        // than typing them again here.
        if ($user->employment_started_on === null || $user->division_id === null || $user->employment_type === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Tanggal Mulai Kerja, Jabatan/Divisi, atau Jenis Hubungan Kerja {$user->name} belum diisi — lengkapi dulu di Manajemen User sebelum mengatur profil gaji.",
            ]);

            return redirect()->route('hrd.profil-gaji.index');
        }

        $profile = EmployeePayrollProfile::where('user_id', $user->id)
            ->latest('effective_from')
            ->with('salaryComponents.payrollComponent')
            ->first();

        $division = Division::findOrFail($user->division_id);

        return Inertia::render('hrd/payroll-profile-edit', [
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'company_id' => $user->company_id,
                'company_name' => $user->company->name,
                'employment_type' => $user->employment_type,
                'employment_started_on' => $user->employment_started_on->toDateString(),
                'division' => [
                    'id' => $division->id,
                    'name' => $division->name,
                ],
            ],
            'profile' => $profile ? [
                'effective_from' => $profile->effective_from->toDateString(),
                'employment_type' => $profile->employment_type,
                'earningComponents' => $profile->salaryComponents
                    ->filter(fn (EmployeeSalaryComponent $component) => $component->payrollComponent->type === 'earning')
                    ->values()
                    ->map(fn (EmployeeSalaryComponent $component) => [
                        'payroll_component_id' => $component->payroll_component_id,
                        'amount' => (string) $component->amount,
                    ]),
                'deductionComponents' => $profile->salaryComponents
                    ->filter(fn (EmployeeSalaryComponent $component) => $component->payrollComponent->type === 'deduction')
                    ->values()
                    ->map(fn (EmployeeSalaryComponent $component) => [
                        'payroll_component_id' => $component->payroll_component_id,
                        'amount' => (string) $component->amount,
                    ]),
            ] : null,
            // Reward Kehadiran Sempurna, Lembur, Potongan Alpha/
            // Keterlambatan, etc. are system-managed (computed fresh from
            // attendance/overtime on every payroll run) — offering them
            // here would let HRD set a fixed monthly amount that then gets
            // paid on top of the calculated one. See
            // PayrollComponent::SYSTEM_CODES.
            'availableEarningComponents' => PayrollComponent::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where('type', 'earning')
                ->whereNotIn('code', PayrollComponent::SYSTEM_CODES)
                ->orderBy('name')
                ->get(['id', 'name']),
            'availableDeductionComponents' => PayrollComponent::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where('type', 'deduction')
                ->whereNotIn('code', PayrollComponent::SYSTEM_CODES)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePayrollProfileRequest $request, User $user, PayrollAuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($user->role === 'karyawan', 404);
        $this->assertOwnCompany($request, $user->company_id);
        abort_if($user->employment_started_on === null || $user->division_id === null || $user->employment_type === null, 422, 'Tanggal Mulai Kerja, Jabatan/Divisi, atau Jenis Hubungan Kerja belum diisi di Manajemen User.');

        $validated = $request->validated();

        $existingProfile = EmployeePayrollProfile::where('user_id', $user->id)->with('salaryComponents')->first();
        $before = $existingProfile ? [
            'division_id' => $existingProfile->division_id,
            'employment_type' => $existingProfile->employment_type,
            'effective_from' => $existingProfile->effective_from->toDateString(),
            'components' => $existingProfile->salaryComponents->map(fn (EmployeeSalaryComponent $component) => [
                'payroll_component_id' => $component->payroll_component_id,
                'amount' => (string) $component->amount,
            ])->all(),
        ] : null;

        $profile = DB::transaction(function () use ($validated, $user, $existingProfile) {
            // One current profile per employee for now — history-aware
            // versioning (multiple effective-dated profiles) lands once the
            // payroll run calculator actually needs to snapshot changes
            // mid-period, not before.
            $profile = EmployeePayrollProfile::firstOrNew(['user_id' => $user->id]);
            $profile->fill([
                'company_id' => $user->company_id,
                'division_id' => $user->division_id,
                'position_id' => $user->position_id,
                // Set once when the profile is first created, then kept
                // unchanged on every later edit — same reasoning as
                // division_id/employment_type: this isn't a per-save input.
                'effective_from' => $existingProfile?->effective_from ?? $user->employment_started_on,
                'employment_started_on' => $user->employment_started_on,
                'employment_type' => $user->employment_type,
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
                'components' => $validated['components'],
            ],
            reason: $validated['reason'] ?? "Profil gaji {$user->name} diperbarui oleh HRD.",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Profil gaji {$user->name} berhasil disimpan."]);

        return redirect()->route('hrd.profil-gaji.index');
    }
}
