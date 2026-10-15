<?php

namespace App\Concerns;

use App\Models\Division;
use Illuminate\Validation\Validator;

trait ValidatesCompanyDivision
{
    /**
     * division_id must actually belong to the selected company_id (the
     * two selects are populated independently on the client, so nothing
     * guarantees that pairing without this).
     */
    protected function assertDivisionBelongsToCompany(Validator $validator, mixed $companyId, mixed $divisionId): void
    {
        if (! $companyId || ! $divisionId) {
            return;
        }

        $companyHasDivision = Division::whereKey($divisionId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $companyHasDivision) {
            $validator->errors()->add('division_id', 'Jabatan/Divisi yang dipilih tidak tersedia untuk instansi ini.');
        }
    }
}
