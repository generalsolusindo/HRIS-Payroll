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

        $hrd = User::create([
            'company_id' => $company->id,
            'position_id' => $position->id,
            'name' => 'HRD General Solusindo',
            'email' => 'hrd@generalsolusindo.com',
            'phone' => '081234567890',
            'password' => 'password',
        ]);

        $hrd->role = 'hrd';
        $hrd->status = 'active';
        $hrd->save();
    }
}
