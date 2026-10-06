<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * HRD/Manager/Finance accounts each belong to exactly one company
 * (User::company_id) and must never read or act on another company's
 * data — role middleware alone (EnsureUserHasRole) only checks the role
 * name, not the tenant boundary. Every controller that loads or mutates
 * a company-scoped record uses this to enforce that boundary explicitly,
 * at the point the record is resolved.
 */
trait ScopesToOwnCompany
{
    protected function assertOwnCompany(Request $request, ?int $companyId): void
    {
        abort_unless($companyId !== null && $companyId === $request->user()->company_id, 403);
    }
}
