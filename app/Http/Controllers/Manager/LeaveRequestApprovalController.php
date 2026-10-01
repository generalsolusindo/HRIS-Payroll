<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestApprovalController extends Controller
{
    public function __construct(
        private readonly LeaveRequestNotifier $notifier,
    ) {}

    public function index(): Response
    {
        $leaveRequests = LeaveRequest::where('status', 'pending_manager')
            ->with(['user:id,name,company_id,position_id', 'user.company:id,name', 'user.position:id,name', 'attachments', 'hrdProcessedBy:id,name'])
            ->oldest()
            ->get();

        return Inertia::render('manager/leave-request-approval', [
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

            $this->assertAwaitingManager($locked);

            $locked->fill([
                'status' => 'approved',
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'approval_note' => $validated['note'] ?? null,
            ])->save();
        });

        $this->notifier->notifyFinalApproved(
            $leaveRequest->refresh()->load(['user.company', 'user.position', 'hrdProcessedBy', 'processedBy']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan disetujui.']);

        return back();
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $leaveRequest, $validated) {
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

            $this->assertAwaitingManager($locked);

            $locked->fill([
                'status' => 'rejected',
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'rejection_reason' => $validated['note'],
            ])->save();
        });

        $this->notifier->notifyRejected(
            $leaveRequest->refresh()->load(['user.company', 'user.position', 'hrdProcessedBy', 'processedBy']),
            'manager',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan ditolak.']);

        return back();
    }

    private function assertAwaitingManager(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status !== 'pending_manager') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan ini sudah diproses sebelumnya, atau belum disetujui HRD.',
            ]);
        }
    }
}
