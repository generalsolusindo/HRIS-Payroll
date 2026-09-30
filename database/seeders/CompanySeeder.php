<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\OfficeLocation;
use App\Models\Position;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generalSolusindo = Company::create(['name' => 'General Solusindo', 'code' => 'GS']);
        $tabinaco = Company::create(['name' => 'Tabinaco', 'code' => 'TBC']);

        $latitude = -7.4433990;
        $longitude = 112.7021927;
        $radiusMeter = 30;

        OfficeLocation::create([
            'company_id' => $generalSolusindo->id,
            'name' => 'Kantor Pusat General Solusindo',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meter' => $radiusMeter,
        ]);

        OfficeLocation::create([
            'company_id' => $tabinaco->id,
            'name' => 'Kantor Tabinaco',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meter' => $radiusMeter,
        ]);

        // Jam operasional diambil dari listing Google Maps General Solusindo
        // (Senin-Sabtu 08.30-16.30, Minggu tutup), dengan toleransi telat 15 menit.
        foreach ([$generalSolusindo, $tabinaco] as $company) {
            WorkSchedule::create([
                'company_id' => $company->id,
                'name' => 'Reguler',
                'jam_masuk' => '08:30:00',
                'jam_pulang' => '16:30:00',
                'toleransi_telat_menit' => 15,
            ]);
        }

        $positionNames = [
            'Sales', 'Marketing', 'Finance', 'Operasional', 'Procurement',
            'Teknisi', 'Internship', 'Admin Store', 'Content Creator',
            'HR', 'Digital Marketing', 'SI Officer',
        ];

        $positions = collect($positionNames)->mapWithKeys(
            fn (string $name) => [$name => Position::create(['name' => $name])->id]
        );

        // Semua posisi berlaku di General Solusindo.
        $generalSolusindo->positions()->attach($positions->values());

        // Tabinaco memakai daftar yang sama, kecuali "Teknisi".
        $tabinaco->positions()->attach($positions->except('Teknisi')->values());
    }
}
