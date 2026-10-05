<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StorePayrollPeriodRequest;
use App\Models\Company;
use App\Models\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPeriodController extends Controller
{
    public function index(): Response
    {
        $periods = PayrollPeriod::with(['company:id,name', 'runs' => fn ($query) => $query->latest('version')->limit(1)])
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (PayrollPeriod $period) => [
                'id' => $period->id,
                'company' => ['id' => $period->company->id, 'name' => $period->company->name],
                'code' => $period->code,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'status' => $period->status,
                'latest_run' => $period->runs->first() ? [
                    'id' => $period->runs->first()->id,
                    'version' => $period->runs->first()->version,
                    'status' => $period->runs->first()->status,
                    'employee_count' => $period->runs->first()->employee_count,
                    'net_total' => (string) $period->runs->first()->net_total,
                ] : null,
            ]);

        return Inertia::render('hrd/payroll-periods', [
            'periods' => $periods,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePayrollPeriodRequest $request): RedirectResponse
    {
        $period = PayrollPeriod::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Periode {$period->name} berhasil dibuat."]);

        return back();
    }
}
