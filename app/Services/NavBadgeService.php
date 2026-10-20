<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollEntryComplaint;
use App\Models\PayrollPeriodDeletionRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Models\UserNavBadgeView;
use Illuminate\Support\Carbon;

/**
 * Powers the small colored dot on sidebar nav items: each badge key below
 * answers "is there anything new or pending in this area the user hasn't
 * opened since it last changed". There's no separate "resolved" state to
 * track — a badge clears the moment the user visits the page it belongs
 * to (see markSeen() calls in that page's controller) and reappears only
 * if the underlying data changes again afterward.
 */
class NavBadgeService
{
    /**
     * @return array<string, string|null>
     */
    private function latestActivityByKey(User $user): array
    {
        $companyId = $user->company_id;

        return match ($user->role) {
            'hrd' => [
                'hrd_izin' => LeaveRequest::whereHas('user', fn ($q) => $q->where('company_id', $companyId))
                    ->where('status', 'pending')
                    ->max('created_at'),
                'hrd_lembur' => OvertimeRequest::where('company_id', $companyId)
                    ->where('status', 'submitted')
                    ->max('created_at'),
                'hrd_payroll_complaints' => PayrollEntryComplaint::whereHas(
                    'entry.run.period',
                    fn ($q) => $q->where('company_id', $companyId),
                )->where('status', 'new')->max('created_at'),
                // HRD manages users across every company, not just their
                // own — same cross-company scope as UserManagementController.
                'hrd_pending_users' => User::where('status', 'pending')
                    ->max('created_at'),
            ],
            'admin' => [
                'manager_izin' => LeaveRequest::whereHas('user', fn ($q) => $q->where('company_id', $companyId))
                    ->where('status', 'pending_manager')
                    ->max('created_at'),
                'manager_payroll' => $this->latestOf(
                    PayrollRun::whereHas('period', fn ($q) => $q->where('company_id', $companyId))
                        ->where('status', 'submitted')
                        ->max('updated_at'),
                    PayrollPeriodDeletionRequest::whereHas('period', fn ($q) => $q->where('company_id', $companyId))
                        ->where('status', 'pending')
                        ->max('requested_at'),
                ),
            ],
            'finance' => [
                'finance_payroll' => PayrollRun::whereHas('period', fn ($q) => $q->where('company_id', $companyId))
                    ->whereIn('status', ['approved', 'locked', 'partially_paid'])
                    ->max('updated_at'),
            ],
            'karyawan' => [
                'karyawan_izin' => LeaveRequest::where('user_id', $user->id)
                    ->whereIn('status', ['approved', 'rejected'])
                    ->max('updated_at'),
                'karyawan_lembur' => OvertimeRequest::where('user_id', $user->id)
                    ->whereIn('status', ['approved', 'rejected'])
                    ->max('updated_at'),
                'karyawan_penggajian' => $this->latestOf(
                    PayrollRun::whereHas('entries', fn ($q) => $q->where('user_id', $user->id))
                        ->whereNotNull('shared_at')
                        ->max('shared_at'),
                    PayrollEntryComplaint::where('user_id', $user->id)
                        ->whereNotNull('resolved_at')
                        ->max('resolved_at'),
                ),
            ],
            default => [],
        };
    }

    private function latestOf(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a > $b ? $a : $b;
    }

    /**
     * @return array<string, bool>
     */
    public function pendingBadges(User $user): array
    {
        $latest = $this->latestActivityByKey($user);

        if ($latest === []) {
            return [];
        }

        $seenAt = UserNavBadgeView::where('user_id', $user->id)
            ->whereIn('badge_key', array_keys($latest))
            ->pluck('last_seen_at', 'badge_key');

        $result = [];
        foreach ($latest as $key => $timestamp) {
            if ($timestamp === null) {
                $result[$key] = false;

                continue;
            }

            $seen = $seenAt->get($key);
            $result[$key] = $seen === null || Carbon::parse($timestamp)->gt($seen);
        }

        return $result;
    }

    public function markSeen(User $user, string $badgeKey): void
    {
        UserNavBadgeView::updateOrCreate(
            ['user_id' => $user->id, 'badge_key' => $badgeKey],
            ['last_seen_at' => now()],
        );
    }
}
