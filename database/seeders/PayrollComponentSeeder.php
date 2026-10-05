<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PayrollComponent;
use Illuminate\Database\Seeder;

class PayrollComponentSeeder extends Seeder
{
    /**
     * A sane starting point per company — HRD can add more (insentif,
     * lembur, BPJS, pajak, dll.) through Manajemen Komponen Gaji itself.
     */
    public function run(): void
    {
        foreach (Company::all() as $company) {
            PayrollComponent::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'BASE'],
                ['name' => 'Gaji Pokok', 'type' => 'earning', 'category' => 'base_salary', 'is_active' => true],
            );
            PayrollComponent::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'ALLOWANCE'],
                ['name' => 'Tunjangan', 'type' => 'earning', 'category' => 'allowance', 'is_active' => true],
            );
        }
    }
}
