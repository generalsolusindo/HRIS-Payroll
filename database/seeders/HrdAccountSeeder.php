<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrdAccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'GS')->firstOrFail();
        $position = Position::where('name', 'HR')->firstOrFail();

        $hrd = User::firstOrNew(['email' => config('seed.hrd.email')]);

        $hrd->fill([
            'company_id' => $company->id,
            'position_id' => $position->id,
            'name' => config('seed.hrd.name'),
            'phone' => '081234567890',
        ]);

        if (! $hrd->exists) {
            $hrd->password = config('seed.user_password');
        }

        $hrd->role = 'hrd';
        $hrd->status = 'active';
        $hrd->save();
    }
}
