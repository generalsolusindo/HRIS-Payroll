<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Services\LeaveRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $leaveRequestService,
    ) {}

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

        return back();
    }
}
