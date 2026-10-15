<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\UpdateCompanySettingsRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
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
        $companies = Company::orderBy('name')->get(['id', 'name', 'telegram_chat_id']);

        return Inertia::render('hrd/company-settings', [
            'companies' => $companies,
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return back()->with('status', "Pengaturan {$company->name} berhasil disimpan.");
    }
}
