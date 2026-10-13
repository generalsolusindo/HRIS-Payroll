<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StoreCompanyCalendarDayRequest;
use App\Models\Company;
use App\Models\CompanyCalendarDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyCalendarDayController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        return Inertia::render('hrd/calendar-days', [
            'days' => CompanyCalendarDay::with('company:id,name')
                ->where('company_id', $request->user()->company_id)
                ->orderByDesc('date')
                ->get(['id', 'company_id', 'date', 'type', 'description'])
                ->map(fn (CompanyCalendarDay $day) => [
                    'id' => $day->id,
                    'company' => ['id' => $day->company->id, 'name' => $day->company->name],
                    'date' => $day->date->toDateString(),
                    'type' => $day->type,
                    'description' => $day->description,
                ]),
            'companies' => Company::where('id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCompanyCalendarDayRequest $request): RedirectResponse
    {
        CompanyCalendarDay::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanggal kalender berhasil ditambahkan.']);

        return back();
    }

    public function destroy(Request $request, CompanyCalendarDay $day): RedirectResponse
    {
        $this->assertOwnCompany($request, $day->company_id);

        $day->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanggal kalender berhasil dihapus.']);

        return back();
    }
}
