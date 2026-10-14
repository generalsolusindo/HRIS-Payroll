<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\UpdateCompanySettingsRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HRD only ever sees and edits their own company's settings — there is no
 * multi-company management role in this application (same boundary as
 * PayrollPolicyController/UserManagementController).
 */
class CompanySettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $company = Company::findOrFail($request->user()->company_id);

        return Inertia::render('hrd/company-settings', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'telegram_chat_id' => $company->telegram_chat_id,
            ],
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $company = Company::findOrFail($request->user()->company_id);

        $company->update($request->validated());

        return back()->with('status', 'Pengaturan perusahaan berhasil disimpan.');
    }
}
