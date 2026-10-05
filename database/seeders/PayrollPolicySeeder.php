<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PayrollPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PayrollPolicySeeder extends Seeder
{
    /**
     * A minimal starting policy per company — empty rules for now (no
     * late-deduction or reward formulas yet, see payroll-schema.md §
     * "Aturan perhitungan"). Required because payroll_runs.payroll_policy_id
     * is not nullable; the run calculator needs one to point to even before
     * any real rule exists.
     */
    public function run(): void
    {
        foreach (Company::all() as $company) {
            PayrollPolicy::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'REGULAR', 'version' => 1],
                ['effective_from' => Carbon::today(), 'rules' => [], 'description' => 'Kebijakan awal — belum ada aturan potongan/reward.'],
            );
        }
    }
}
