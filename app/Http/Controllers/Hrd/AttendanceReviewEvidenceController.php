<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\AttendanceReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReviewEvidenceController extends Controller
{
    use ScopesToOwnCompany;

    public function show(Request $request, AttendanceReview $review): StreamedResponse
    {
        // Gated by the route's auth/active/role:hrd middleware — same
        // access level as the correction screen itself, plus an explicit
        // tenant check: role alone doesn't imply "same company".
        $this->assertOwnCompany($request, $review->company_id);

        abort_if($review->evidence_path === null, 404);
        abort_unless(Storage::disk('attendance_evidence')->exists($review->evidence_path), 404);

        return Storage::disk('attendance_evidence')->response($review->evidence_path);
    }
}
