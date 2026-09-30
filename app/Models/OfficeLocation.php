<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeLocation extends Model
{
    protected $fillable = ['company_id', 'name', 'latitude', 'longitude', 'radius_meter'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Great-circle distance in meters between this office and a given
     * coordinate, using the Haversine formula.
     */
    public function distanceInMetersFrom(float $latitude, float $longitude): float
    {
        $earthRadiusMeters = 6_371_000;

        $latitudeDelta = deg2rad($latitude - (float) $this->latitude);
        $longitudeDelta = deg2rad($longitude - (float) $this->longitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad((float) $this->latitude)) * cos(deg2rad($latitude))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
