<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StorePayrollComponentRequest;
use App\Http\Requests\Hrd\UpdatePayrollComponentRequest;
use App\Models\Company;
use App\Models\PayrollComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollComponentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('hrd/payroll-components', [
            'components' => PayrollComponent::with('company:id,name')
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name', 'type', 'category', 'is_active']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePayrollComponentRequest $request): RedirectResponse
    {
        $component = PayrollComponent::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Komponen {$component->name} berhasil dibuat."]);

        return back();
    }

    public function update(UpdatePayrollComponentRequest $request, PayrollComponent $payrollComponent): RedirectResponse
    {
        $payrollComponent->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Komponen {$payrollComponent->name} berhasil diperbarui."]);

        return back();
    }

    public function destroy(PayrollComponent $payrollComponent): RedirectResponse
    {
        if ($payrollComponent->employeeSalaryComponents()->exists() || $payrollComponent->entryLines()->exists()) {
            throw ValidationException::withMessages([
                'component' => "Komponen {$payrollComponent->name} masih dipakai di profil gaji atau histori payroll — nonaktifkan saja, bukan hapus.",
            ]);
        }

        $name = $payrollComponent->name;
        $payrollComponent->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Komponen {$name} berhasil dihapus."]);

        return back();
    }
}
