<?php

namespace App\Console\Commands;

use App\Models\OfficeLocation;
use Illuminate\Console\Command;

class AttendanceLocationStatus extends Command
{
    protected $signature = 'attendance:location-status';

    protected $description = 'Tampilkan titik dan radius kantor untuk pemeriksaan konfigurasi absensi (tanpa mengubah data)';

    public function handle(): int
    {
        $this->table(
            ['ID', 'Perusahaan ID', 'Kantor', 'Latitude', 'Longitude', 'Radius (m)'],
            OfficeLocation::orderBy('company_id')->get()->map(fn (OfficeLocation $office) => [
                $office->id, $office->company_id, $office->name,
                $office->latitude, $office->longitude, min($office->radius_meter, OfficeLocation::MAX_ATTENDANCE_RADIUS_METERS),
            ])->all(),
        );
        $this->info('Batas ketelitian lokasi: maksimal ±50 m. Ini berbeda dari radius kantor.');

        return self::SUCCESS;
    }
}
