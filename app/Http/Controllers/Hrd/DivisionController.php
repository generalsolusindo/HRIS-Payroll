<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StoreDivisionRequest;
use App\Http\Requests\Hrd\UpdateDivisionRequest;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        return Inertia::render('hrd/divisions', [
            'divisions' => Division::where('company_id', $request->user()->company_id)
                ->orderBy('name')
                ->get(['id', 'name', 'is_active', 'base_salary']),
        ]);
    }

    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        $division = Division::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Divisi {$division->name} berhasil dibuat."]);

        return back();
    }

    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $this->assertOwnCompany($request, $division->company_id);

        $division->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Divisi {$division->name} berhasil diperbarui."]);

        return back();
    }

    public function destroy(Request $request, Division $division): RedirectResponse
    {
        $this->assertOwnCompany($request, $division->company_id);

        if ($division->employeePayrollProfiles()->exists()) {
            throw ValidationException::withMessages([
                'division' => "Divisi {$division->name} masih dipakai oleh profil gaji karyawan — nonaktifkan saja, bukan hapus.",
            ]);
        }

        $name = $division->name;
        $division->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Divisi {$name} berhasil dihapus."]);

        return back();
    }
}
