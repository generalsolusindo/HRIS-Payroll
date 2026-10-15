<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class ManagerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'General Solusindo')->firstOrFail();
        $position = Position::where('name', 'Manager')->firstOrFail();

        // Password is only set when the account is first created — a
        // re-run must never reset a password the Manager has since
        // changed themselves.
        $manager = User::firstOrNew(['email' => config('seed.manager.email')]);

        $manager->fill([
            'company_id' => $company->id,
            'position_id' => $position->id,
            'name' => config('seed.manager.name'),
            'phone' => '081234567891',
        ]);

        if (! $manager->exists) {
            $manager->password = config('seed.user_password');
        }

        // Role 'admin' is global in scope — same as 'hrd', not restricted
        // to the company this account happens to be attached to.
        $manager->role = 'admin';
        $manager->status = 'active';
        $manager->save();
    }
}
