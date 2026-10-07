<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StorePayrollComponentRequest;
use App\Http\Requests\Hrd\UpdatePayrollComponentRequest;
use App\Models\PayrollComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollComponentController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        return Inertia::render('hrd/payroll-components', [
            'components' => PayrollComponent::where('company_id', $request->user()->company_id)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'category', 'is_active']),
        ]);
    }

    public function store(StorePayrollComponentRequest $request): RedirectResponse
    {
        $component = PayrollComponent::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Komponen {$component->name} berhasil dibuat."]);

        return back();
    }

    public function update(UpdatePayrollComponentRequest $request, PayrollComponent $payrollComponent): RedirectResponse
    {
        $this->assertOwnCompany($request, $payrollComponent->company_id);

        $payrollComponent->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Komponen {$payrollComponent->name} berhasil diperbarui."]);

        return back();
    }

    public function destroy(Request $request, PayrollComponent $payrollComponent): RedirectResponse
    {
        $this->assertOwnCompany($request, $payrollComponent->company_id);

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
