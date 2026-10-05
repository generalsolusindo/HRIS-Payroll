<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StoreDivisionRequest;
use App\Http\Requests\Hrd\UpdateDivisionRequest;
use App\Models\Company;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('hrd/divisions', [
            'divisions' => Division::with('company:id,name')
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name', 'is_active']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        $division = Division::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Divisi {$division->name} berhasil dibuat."]);

        return back();
    }

    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Divisi {$division->name} berhasil diperbarui."]);

        return back();
    }

    public function destroy(Division $division): RedirectResponse
    {
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
