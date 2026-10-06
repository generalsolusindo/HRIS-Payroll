<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimeRequestRequest;
use App\Models\OvertimeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $overtimeRequests = $request->user()->overtimeRequests()
            ->with('processedBy:id,name')
            ->latest('starts_at')
            ->get();

        return Inertia::render('lembur', ['overtimeRequests' => $overtimeRequests]);
    }

    public function store(StoreOvertimeRequestRequest $request): RedirectResponse
    {
        OvertimeRequest::create([
            'user_id' => $request->user()->id,
            'company_id' => $request->user()->company_id,
            'starts_at' => $request->validated()['starts_at'],
            'ends_at' => $request->validated()['ends_at'],
            'break_minutes' => $request->validated()['break_minutes'] ?? 0,
            'reason' => $request->validated()['reason'],
            'status' => 'submitted',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan lembur dikirim, menunggu persetujuan HRD.']);

        return back();
    }
}
