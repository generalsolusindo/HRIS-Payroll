<?php

namespace App\Concerns;

use App\Models\Company;
use Illuminate\Validation\Validator;

trait ValidatesCompanyPosition
{
    /**
     * position_id must actually belong to the selected company_id (the
     * two selects are populated independently on the client, so nothing
     * guarantees that pairing without this).
     */
    protected function assertPositionBelongsToCompany(Validator $validator, mixed $companyId, mixed $positionId): void
    {
        if (! $companyId || ! $positionId) {
            return;
        }

        $companyHasPosition = Company::whereKey($companyId)
            ->whereHas('positions', fn ($query) => $query->whereKey($positionId))
            ->exists();

        if (! $companyHasPosition) {
            $validator->errors()->add('position_id', 'Jabatan/Divisi yang dipilih tidak tersedia untuk instansi ini.');
        }
    }
}
