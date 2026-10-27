<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\UpdateCompanySettingsRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HRD manages settings for every instansi in the system (General
 * Solusindo and Tabinaco share this one HRD role) — same cross-company
 * scope as UserManagementController, deliberately not tenant isolated
 * like most other HRD-scoped resources (PayrollPolicyController etc.).
 */
class CompanySettingsController extends Controller
{
    public function index(): Response
    {
        $companies = Company::orderBy('name')->get([
            'id', 'name', 'telegram_chat_id',
            'legal_name', 'code', 'address', 'city', 'director_name', 'director_signature_path',
        ]);

        return Inertia::render('hrd/company-settings', [
            'companies' => $companies,
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request, Company $company): RedirectResponse
    {
        $validated = $request->safe()->except('director_signature');

        if ($request->hasFile('director_signature')) {
            if ($company->director_signature_path) {
                Storage::disk('company_signatures')->delete($company->director_signature_path);
            }

            $validated['director_signature_path'] = $request->file('director_signature')
                ->store((string) $company->id, 'company_signatures');
        }

        $company->update($validated);

        return back()->with('status', "Pengaturan {$company->name} berhasil disimpan.");
    }
}
