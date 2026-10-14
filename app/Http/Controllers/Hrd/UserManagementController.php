<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StoreUserRequest;
use App\Http\Requests\Hrd\UpdateUserRequest;
use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $users = User::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['company:id,name', 'position:id,name'])
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
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'company' => ['id' => $user->company->id, 'name' => $user->company->name],
                'position' => ['id' => $user->position->id, 'name' => $user->position->name],
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
            'companies' => $this->companiesWithPositions($request->user()->company_id),
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
            'position_id' => $validated['position_id'],
            'employment_type' => $validated['employment_type'] ?? null,
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
        $this->assertOwnCompany($request, $user->company_id);

        $validated = $request->validated();
        $isSelf = $user->id === $request->user()->id;

        $user->fill([
            'company_id' => $validated['company_id'],
            'position_id' => $validated['position_id'],
            'employment_type' => $validated['employment_type'] ?? $user->employment_type,
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
        $this->assertOwnCompany($request, $user->company_id);
        abort_if($user->id === $request->user()->id, 403, 'Tidak bisa menghapus akun sendiri.');

        $name = $user->name;
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pengguna {$name} berhasil dihapus."]);

        return back();
    }

    /**
     * HRD only ever creates/edits users within their own company — the
     * dropdown this feeds must never offer another tenant's company.
     *
     * @return Collection<int, array{id: int<0, max>, name: string, positions: Collection<int, array{id: int<0, max>, name: string}>}>
     */
    private function companiesWithPositions(int $companyId)
    {
        return Company::with(['positions' => fn ($query) => $query->orderBy('name')])
            ->where('id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $company) => [
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                'positions' => $company->positions->map(fn (Position $position) => [
                    'id' => (int) $position->id,
                    'name' => (string) $position->name,
                ]),
            ]);
    }
}
