<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeApprovalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('hrd/employee-approval', [
            'pendingUsers' => User::where('status', 'pending')
                ->with(['company:id,name', 'position:id,name'])
                ->oldest()
                ->get(['id', 'company_id', 'position_id', 'name', 'email', 'phone', 'created_at']),
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->status = 'active';
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} disetujui."]);

        return back();
    }

    public function reject(User $user): RedirectResponse
    {
        $user->status = 'rejected';
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} ditolak."]);

        return back();
    }
}
