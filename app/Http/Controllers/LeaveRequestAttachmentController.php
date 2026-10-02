<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequestAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestAttachmentController extends Controller
{
    public function show(LeaveRequestAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->leaveRequest);

        abort_unless(
            Storage::disk('leave_attachments')->exists($attachment->file_path),
            404,
        );

        return Storage::disk('leave_attachments')->response(
            $attachment->file_path,
            $attachment->file_name,
        );
    }
}
