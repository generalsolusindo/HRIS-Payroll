<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    /**
     * The owner can view their own submission; HRD and Manager can view
     * any submission (needed to review it, and to look back at ones
     * they've already decided).
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->id === $leaveRequest->user_id
            || in_array($user->role, ['hrd', 'admin'], true);
    }
}
