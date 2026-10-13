<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    /**
     * The owner can view their own submission; HRD and Manager can view
     * any submission from their own company (needed to review it, and
     * to look back at ones they've already decided) — but never another
     * company's, since role alone doesn't carry the tenant boundary.
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->id === $leaveRequest->user_id) {
            return true;
        }

        return in_array($user->role, ['hrd', 'admin'], true)
            && $user->company_id === $leaveRequest->user->company_id;
    }
}
