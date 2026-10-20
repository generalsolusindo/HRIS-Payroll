<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Services\LeaveRequestNotifier;
use App\Services\LeaveRequestService;
use App\Services\NavBadgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $leaveRequestService,
        private readonly LeaveRequestNotifier $notifier,
    ) {}

    public function index(Request $request, NavBadgeService $navBadges): Response
    {
        $navBadges->markSeen($request->user(), 'karyawan_izin');

        $leaveRequests = $request->user()->leaveRequests()
            ->with(['processedBy:id,name', 'hrdProcessedBy:id,name'])
            ->latest('leave_date')
            ->get();

        return Inertia::render('izin', [
            'leaveRequests' => $leaveRequests,
        ]);
    }

    public function show(LeaveRequest $leaveRequest): Response
    {
        Gate::authorize('view', $leaveRequest);

        $leaveRequest->load(['user:id,name,company_id,division_id', 'user.company:id,name', 'user.division:id,name', 'attachments', 'hrdProcessedBy:id,name', 'processedBy:id,name']);

        return Inertia::render('izin-detail', [
            'leaveRequest' => $leaveRequest,
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        $leaveRequest = LeaveRequest::create([
            'request_number' => $this->leaveRequestService->generateRequestNumber(),
            'user_id' => $request->user()->id,
            ...$request->leaveRequestAttributes(),
        ]);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('attachments', []);

        foreach ($files as $file) {
            $path = $file->store((string) $leaveRequest->id, 'leave_attachments');

            abort_if($path === false, 500, 'Gagal menyimpan lampiran. Coba lagi.');

            LeaveRequestAttachment::create([
                'leave_request_id' => $leaveRequest->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }

        $this->notifier->notifySubmitted($leaveRequest->load(['user.company', 'user.division']));

        return back();
    }
}
