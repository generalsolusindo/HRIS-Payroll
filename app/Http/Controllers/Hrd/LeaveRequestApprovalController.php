<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\FiltersLeaveRequests;
use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestNotifier;
use App\Services\NavBadgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestApprovalController extends Controller
{
    use FiltersLeaveRequests, ScopesToOwnCompany;

    private const PENDING_STATUS = 'pending';

    public function __construct(
        private readonly LeaveRequestNotifier $notifier,
    ) {}

    /**
     * HRD's own queue — only pengajuan still awaiting the HRD stage.
     */
    public function index(Request $request, NavBadgeService $navBadges): Response
    {
        $companyId = $request->user()->company_id;
        $navBadges->markSeen($request->user(), 'hrd_izin');

        $leaveRequests = $this->filteredLeaveRequestsQuery($request, $companyId)
            ->where('status', self::PENDING_STATUS)
            ->with(['user:id,name,company_id,division_id', 'user.company:id,name', 'user.division:id,name', 'attachments'])
            ->oldest()
            ->get();

        return Inertia::render('hrd/leave-request-approval', [
            'leaveRequests' => $leaveRequests,
            'stats' => $this->leaveRequestStats($companyId, self::PENDING_STATUS),
            'filters' => $request->only('search', 'type', 'division_id'),
            'divisions' => $this->divisionsForCompany($companyId),
            'leaveTypes' => $this->leaveTypeOptions(),
        ]);
    }

    /**
     * Full searchable archive — every status, every pengajuan this
     * company has ever had, not just what's currently awaiting HRD.
     */
    public function riwayat(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        $leaveRequests = $this->filteredLeaveRequestsQuery($request, $companyId)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->with(['user:id,name,company_id,division_id', 'user.company:id,name', 'user.division:id,name', 'hrdProcessedBy:id,name', 'processedBy:id,name'])
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('hrd/leave-request-history', [
            'leaveRequests' => $leaveRequests,
            'stats' => $this->leaveRequestStats($companyId, self::PENDING_STATUS),
            'filters' => $request->only('search', 'type', 'division_id', 'status'),
            'divisions' => $this->divisionsForCompany($companyId),
            'leaveTypes' => $this->leaveTypeOptions(),
        ]);
    }

    public function laporan(Request $request): StreamedResponse
    {
        $companyId = $request->user()->company_id;

        $query = $this->filteredLeaveRequestsQuery($request, $companyId)
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->toString()),
            );

        return $this->streamLeaveRequestsCsv($query, 'laporan-izin-'.now()->format('Y-m-d').'.csv');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->assertOwnCompany($request, $leaveRequest->user->company_id);
        $this->assertNotOwnRequest($request, $leaveRequest);

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

        $this->notifier->notifyHrdApproved(
            $leaveRequest->refresh()->load(['user.company', 'user.division', 'hrdProcessedBy']),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengajuan disetujui, menunggu keputusan Manager.',
        ]);

        return back();
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->assertOwnCompany($request, $leaveRequest->user->company_id);
        $this->assertNotOwnRequest($request, $leaveRequest);

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

        $this->notifier->notifyRejected(
            $leaveRequest->refresh()->load(['user.company', 'user.division', 'hrdProcessedBy']),
            'hrd',
        );

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

    /**
     * New submissions from HRD/Manager are already blocked at the
     * StoreLeaveRequestRequest level, but this is the actual control
     * boundary — it must hold even if a role changed after submission,
     * not just rely on the submit-time check never having let one through.
     */
    private function assertNotOwnRequest(Request $request, LeaveRequest $leaveRequest): void
    {
        abort_if($leaveRequest->user_id === $request->user()->id, 403, 'Tidak dapat memproses pengajuan milik sendiri.');
    }
}
