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
        $components = PayrollComponent::where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_active', 'code'])
            ->map(fn (PayrollComponent $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'is_active' => $component->is_active,
                // Tells HRD, without exposing the raw internal code, whether
                // this component shows up in the Profil Gaji dropdown (a
                // manual earning) or is computed automatically by the
                // payroll run from attendance/overtime (see SYSTEM_CODES).
                'is_system_managed' => in_array($component->code, PayrollComponent::SYSTEM_CODES, true),
            ]);

        return Inertia::render('hrd/payroll-components', [
            'components' => $components,
        ]);
    }

    public function store(StorePayrollComponentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $companyId = $request->user()->company_id;

        $component = PayrollComponent::create([
            ...$validated,
            'company_id' => $companyId,
            'code' => PayrollComponent::generateCode($companyId, $validated['name']),
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
