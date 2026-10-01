<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestApprovalController extends Controller
{
    public function index(): Response
    {
        $leaveRequests = LeaveRequest::where('status', 'pending')
            ->with(['user:id,name,company_id,position_id', 'user.company:id,name', 'user.position:id,name', 'attachments'])
            ->oldest()
            ->get();

        return Inertia::render('hrd/leave-request-approval', [
            'leaveRequests' => $leaveRequests,
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $leaveRequest, $validated) {
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

            $this->assertStillPending($locked);

            $locked->fill([
                'status' => 'pending_manager',
                'hrd_processed_by' => $request->user()->id,
                'hrd_processed_at' => now(),
                'hrd_decision' => 'approved',
                'hrd_note' => $validated['note'] ?? null,
            ])->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengajuan disetujui, menunggu keputusan Manager.',
        ]);

        return back();
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $leaveRequest, $validated) {
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

            $this->assertStillPending($locked);

            $now = now();

            $locked->fill([
                'status' => 'rejected',
                'hrd_processed_by' => $request->user()->id,
                'hrd_processed_at' => $now,
                'hrd_decision' => 'rejected',
                'hrd_note' => $validated['note'],
                // A rejection by HRD is final — it never reaches the
                // Manager stage, so it's also recorded as the final
                // decision.
                'processed_by' => $request->user()->id,
                'processed_at' => $now,
                'rejection_reason' => $validated['note'],
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan ditolak.']);

        return back();
    }

    private function assertStillPending(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan ini sudah diproses sebelumnya.',
            ]);
        }
    }
}
