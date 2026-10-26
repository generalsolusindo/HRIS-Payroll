<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StoreUserRequest;
use App\Http\Requests\Hrd\UpdateUserRequest;
use App\Models\Company;
use App\Models\Division;
use App\Models\EmployeeDocument;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\User;
use App\Services\NavBadgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request, NavBadgeService $navBadges): Response
    {
        $navBadges->markSeen($request->user(), 'hrd_pending_users');
        $search = trim((string) $request->string('search'));

        // HRD manages users across every instansi in the system, not just
        // their own — see companiesWithDivisions() below for the same
        // cross-company scope applied to the Instansi dropdown.
        $users = User::query()
            ->with(['company:id,name', 'division:id,name'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'employee_number' => $user->employee_number,
                'employment_type' => $user->employment_type,
                'employment_started_on' => $user->employment_started_on?->toDateString(),
                'probation_ends_on' => $user->probation_ends_on?->toDateString(),
                'is_payroll_eligible' => $user->is_payroll_eligible,
                'is_allowance_eligible' => $user->is_allowance_eligible,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'company' => ['id' => $user->company->id, 'name' => $user->company->name],
                'division' => $user->division ? ['id' => $user->division->id, 'name' => $user->division->name] : null,
                // bank_account_number is deliberately left out of this list —
                // it's the one genuinely sensitive field, so it's treated
                // like the password field: write-only from the edit form,
                // never round-tripped back to the browser for every row.
                'bank_name' => $user->bank_name,
                'bank_account_holder' => $user->bank_account_holder,
            ]);

        return Inertia::render('hrd/user-management', [
            'users' => $users,
            'search' => $search,
            'companies' => $this->companiesWithDivisions(),
        ]);
    }

    /**
     * Full detail view for one employee — personal/employment data,
     * contract dates, a read-only snapshot of their current salary
     * profile (editing that stays on the Profil Gaji screen, which is
     * the one place it's ever written), and their optional documents.
     */
    public function show(User $user): Response
    {
        $user->loadMissing([
            'company:id,name',
            'division:id,name',
            'documents' => fn ($query) => $query->latest(),
            'documents.uploadedBy:id,name',
        ]);

        $profile = EmployeePayrollProfile::where('user_id', $user->id)
            ->latest('effective_from')
            ->with('salaryComponents.payrollComponent:id,name,type')
            ->first();

        return Inertia::render('hrd/user-detail', [
            'employee' => [
                'id' => $user->id,
                'employee_number' => $user->employee_number,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'company' => ['id' => $user->company->id, 'name' => $user->company->name],
                'division' => $user->division ? ['id' => $user->division->id, 'name' => $user->division->name] : null,
                'employment_type' => $user->employment_type,
                'employment_started_on' => $user->employment_started_on?->toDateString(),
                'probation_ends_on' => $user->probation_ends_on?->toDateString(),
                'contract_ends_on' => $user->contract_ends_on?->toDateString(),
                'employment_ended_on' => $user->employment_ended_on?->toDateString(),
                'is_payroll_eligible' => $user->is_payroll_eligible,
                'is_allowance_eligible' => $user->is_allowance_eligible,
                'bank_name' => $user->bank_name,
                'bank_account_holder' => $user->bank_account_holder,
            ],
            'salaryProfile' => $profile ? [
                'division_name' => $user->division?->name,
                'components' => $profile->salaryComponents->map(fn (EmployeeSalaryComponent $component) => [
                    'name' => $component->payrollComponent->name,
                    'type' => $component->payrollComponent->type,
                    'amount' => (string) $component->amount,
                ]),
                'monthly_total' => (string) $profile->salaryComponents->sum(
                    fn (EmployeeSalaryComponent $component) => $component->payrollComponent->type === 'deduction'
                        ? -$component->amount : $component->amount
                ),
            ] : null,
            'documents' => $user->documents->map(fn (EmployeeDocument $document) => [
                'id' => $document->id,
                'type' => $document->type,
                'label' => $document->label,
                'file_name' => $document->file_name,
                'file_size' => $document->file_size,
                'uploaded_by_name' => $document->uploadedBy?->name,
                'created_at' => $document->created_at->toIso8601String(),
            ]),
            'documentTypes' => EmployeeDocument::TYPES,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // role/status are deliberately excluded from User's own $fillable
        // (see the #[Fillable] attribute on the model) so nothing else in
        // the app can mass-assign them by accident. This is the one place
        // that's allowed to set them, and it does so explicitly.
        $user = new User([
            'company_id' => $validated['company_id'],
            'division_id' => $validated['division_id'],
            'employment_type' => $validated['employment_type'] ?? null,
            'employment_started_on' => $validated['employment_started_on'],
            'probation_ends_on' => $validated['probation_ends_on'] ?? null,
            'contract_ends_on' => $validated['contract_ends_on'] ?? null,
            'employment_ended_on' => $validated['employment_ended_on'] ?? null,
            'is_payroll_eligible' => $validated['is_payroll_eligible'],
            'is_allowance_eligible' => $validated['is_allowance_eligible'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
            // The model casts this ('hashed'), so it's safe to pass plain.
            'password' => $validated['password'],
        ]);
        $user->forceFill([
            'role' => $validated['role'],
            'status' => $validated['status'],
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pengguna {$user->name} berhasil dibuat."]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $isSelf = $user->id === $request->user()->id;

        $user->fill([
            'company_id' => $validated['company_id'],
            'division_id' => $validated['division_id'],
            'employment_type' => $validated['employment_type'] ?? $user->employment_type,
            'employment_started_on' => $validated['employment_started_on'],
            'probation_ends_on' => $validated['probation_ends_on'] ?? null,
            'contract_ends_on' => $validated['contract_ends_on'] ?? null,
            'employment_ended_on' => $validated['employment_ended_on'] ?? null,
            'is_payroll_eligible' => $validated['is_payroll_eligible'],
            'is_allowance_eligible' => $validated['is_allowance_eligible'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        // Blank means "don't change" — the same write-only treatment as
        // password, since this is the one genuinely sensitive bank field
        // and the edit form never receives the existing value to prefill.
        if (! empty($validated['bank_account_number'])) {
            $user->bank_account_number = $validated['bank_account_number'];
        }

        // An HRD account editing its own row can't demote or deactivate
        // itself here — that's a lockout with no recovery path from this
        // screen. Everything else about their own row remains editable.
        if (! $isSelf) {
            $user->role = $validated['role'];
            $user->status = $validated['status'];
        }

        DB::transaction(fn () => $user->save());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Data {$user->name} berhasil diperbarui."]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Tidak bisa menghapus akun sendiri.');

        $name = $user->name;
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pengguna {$name} berhasil dihapus."]);

        return back();
    }

    /**
     * HRD manages users for every instansi in the system (General
     * Solusindo and Tabinaco share this one HRD role), so this dropdown
     * lists every company, not just the acting HRD's own.
     *
     * @return Collection<int, array{id: int<0, max>, name: string, divisions: Collection<int, array{id: int<0, max>, name: string}>}>
     */
    private function companiesWithDivisions()
    {
        return Company::with(['divisions' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $company) => [
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                'divisions' => $company->divisions->map(fn (Division $division) => [
                    'id' => (int) $division->id,
                    'name' => (string) $division->name,
                ]),
            ]);
    }
}
