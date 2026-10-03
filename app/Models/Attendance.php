<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $tanggal
 * @property Carbon|null $clock_in_time
 * @property string|null $clock_in_status
 * @property int|null $clock_in_late_minutes
 * @property string|null $clock_in_photo_path
 * @property bool|null $clock_in_face_match
 * @property string|null $clock_in_face_distance
 * @property Carbon|null $clock_out_time
 * @property string|null $clock_out_status
 * @property int|null $clock_out_early_minutes
 * @property string|null $clock_out_photo_path
 * @property bool|null $clock_out_face_match
 * @property string|null $clock_out_face_distance
 */
class Attendance extends Model
{
    protected $fillable = [
        'user_id', 'tanggal',
        'clock_in_time', 'clock_in_latitude', 'clock_in_longitude', 'clock_in_accuracy_meter', 'clock_in_distance_meter', 'clock_in_photo_path', 'clock_in_status', 'clock_in_late_minutes', 'clock_in_face_match', 'clock_in_face_distance',
        'clock_out_time', 'clock_out_latitude', 'clock_out_longitude', 'clock_out_accuracy_meter', 'clock_out_distance_meter', 'clock_out_photo_path', 'clock_out_status', 'clock_out_early_minutes', 'clock_out_face_match', 'clock_out_face_distance',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'clock_in_time' => 'datetime',
            'clock_out_time' => 'datetime',
            'clock_in_late_minutes' => 'integer',
            'clock_out_early_minutes' => 'integer',
            'clock_in_latitude' => 'decimal:7',
            'clock_in_longitude' => 'decimal:7',
            'clock_out_latitude' => 'decimal:7',
            'clock_out_longitude' => 'decimal:7',
            'clock_in_face_match' => 'boolean',
            'clock_in_face_distance' => 'decimal:4',
            'clock_out_face_match' => 'boolean',
            'clock_out_face_distance' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Signed, expiring URL for the clock-in selfie, since these photos
     * are private and never served from a guessable public path.
     */
    public function clockInPhotoUrl(): ?string
    {
        return $this->photoUrl($this->clock_in_photo_path);
    }

    public function clockOutPhotoUrl(): ?string
    {
        return $this->photoUrl($this->clock_out_photo_path);
    }

    private function photoUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return Storage::disk('attendance_photos')->temporaryUrl($path, now()->addMinutes(5));
    }
}
