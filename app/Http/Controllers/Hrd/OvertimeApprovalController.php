<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeApprovalController extends Controller
{
    public function index(): Response
    {
        $overtimeRequests = OvertimeRequest::where('status', 'submitted')
            ->with(['user:id,name,company_id,position_id', 'user.company:id,name', 'user.position:id,name'])
            ->oldest('starts_at')
            ->get()
            ->map(fn (OvertimeRequest $overtimeRequest) => [
                'id' => $overtimeRequest->id,
                'user_name' => $overtimeRequest->user->name,
                'company_name' => $overtimeRequest->user->company->name,
                'position_name' => $overtimeRequest->user->position->name,
                'starts_at' => $overtimeRequest->starts_at->toIso8601String(),
                'ends_at' => $overtimeRequest->ends_at->toIso8601String(),
                'break_minutes' => $overtimeRequest->break_minutes,
                'requested_minutes' => $this->rawMinutes($overtimeRequest),
                'reason' => $overtimeRequest->reason,
            ]);

        return Inertia::render('hrd/overtime-approval', ['overtimeRequests' => $overtimeRequests]);
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $this->assertAwaitingDecision($overtimeRequest);

        $validated = $request->validate([
            'approved_minutes' => ['required', 'integer', 'min:0', 'max:'.$this->rawMinutes($overtimeRequest)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $overtimeRequest->fill([
            'status' => 'approved',
            'approved_minutes' => $validated['approved_minutes'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'decision_note' => $validated['note'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lembur disetujui.']);

        return back();
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $this->assertAwaitingDecision($overtimeRequest);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $overtimeRequest->fill([
            'status' => 'rejected',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'decision_note' => $validated['note'],
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lembur ditolak.']);

        return back();
    }

    private function assertAwaitingDecision(OvertimeRequest $overtimeRequest): void
    {
        if ($overtimeRequest->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan ini sudah diproses sebelumnya.',
            ]);
        }
    }

    private function rawMinutes(OvertimeRequest $overtimeRequest): int
    {
        return max(0, (int) $overtimeRequest->starts_at->diffInMinutes($overtimeRequest->ends_at) - $overtimeRequest->break_minutes);
    }
}
