<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StoreCompanyCalendarDayRequest;
use App\Http\Requests\Hrd\UpdateCompanyCalendarDayRequest;
use App\Models\Company;
use App\Models\CompanyCalendarDay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompanyCalendarDayController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        $employeesById = User::where('company_id', $request->user()->company_id)
            ->where('role', 'karyawan')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

        return Inertia::render('hrd/calendar-days', [
            'days' => CompanyCalendarDay::with('company:id,name')
                ->where('company_id', $request->user()->company_id)
                ->orderByDesc('date')
                ->get(['id', 'company_id', 'date', 'type', 'allowed_user_ids', 'description'])
                ->map(fn (CompanyCalendarDay $day) => [
                    'id' => $day->id,
                    'company' => ['id' => $day->company->id, 'name' => $day->company->name],
                    'date' => $day->date->toDateString(),
                    'type' => $day->type,
                    'allowed_user_ids' => $day->allowed_user_ids,
                    'allowed_employee_names' => $day->allowed_user_ids !== null
                        ? collect($day->allowed_user_ids)->map(fn (int $id) => $employeesById->get($id)?->name ?? '—')->all()
                        : null,
                    'description' => $day->description,
                ]),
            'companies' => Company::where('id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']),
            'employees' => $employeesById->values()->map(fn (User $employee) => ['id' => $employee->id, 'name' => $employee->name])->values(),
        ]);
    }

    public function store(StoreCompanyCalendarDayRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $startsOn = Carbon::parse($validated['date']);
        $endsOn = Carbon::parse($validated['ends_on'] ?? $validated['date']);
        // Meaningful for every type, not just "workday" — see
        // CompanyWorkCalendar::dayType() for how the same list means
        // "exclusively open to" on a workday row vs. "exempted from the
        // closure" on a holiday/rest_day row.
        $allowedUserIds = $validated['allowed_user_ids'] ?? null;

        DB::transaction(function () use ($request, $validated, $startsOn, $endsOn, $allowedUserIds): void {
            Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();

            for ($date = $startsOn->copy(); $date->lte($endsOn); $date->addDay()) {
                // updateOrCreate, not create — re-marking a date that's
                // already part of an earlier entry (e.g. extending a
                // holiday range) replaces it rather than erroring on a
                // duplicate unique date.
                CompanyCalendarDay::updateOrCreate(
                    ['company_id' => $validated['company_id'], 'date' => $date->toDateString()],
                    [
                        'type' => $validated['type'],
                        'allowed_user_ids' => $allowedUserIds,
                        'description' => $validated['description'] ?? null,
                    ],
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanggal kalender berhasil ditambahkan.']);

        return back();
    }

    public function update(UpdateCompanyCalendarDayRequest $request, CompanyCalendarDay $day): RedirectResponse
    {
        $this->assertOwnCompany($request, $day->company_id);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $day, $validated): void {
            Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            CompanyCalendarDay::whereKey($day->id)->lockForUpdate()->firstOrFail()->update($validated);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanggal kalender berhasil diperbarui.']);

        return back();
    }

    public function destroy(Request $request, CompanyCalendarDay $day): RedirectResponse
    {
        $this->assertOwnCompany($request, $day->company_id);

        DB::transaction(function () use ($request, $day): void {
            Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            CompanyCalendarDay::whereKey($day->id)->lockForUpdate()->firstOrFail()->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanggal kalender berhasil dihapus.']);

        return back();
    }
}
