<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinanceAccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'GS')->firstOrFail();
        $position = Position::where('name', 'Finance')->firstOrFail();

        // Password is only set when the account is first created — a
        // re-run must never reset a password Finance has since changed
        // themselves.
        $finance = User::firstOrNew(['email' => config('seed.finance.email')]);

        $finance->fill([
            'company_id' => $company->id,
            'position_id' => $position->id,
            'name' => config('seed.finance.name'),
            'phone' => '081234567892',
        ]);

        if (! $finance->exists) {
            $finance->password = config('seed.user_password');
        }

        // Role 'finance' is global in scope — same as 'hrd'/'admin', not
        // restricted to the company this account happens to be attached to.
        $finance->role = 'finance';
        $finance->status = 'active';
        $finance->save();
    }
}
