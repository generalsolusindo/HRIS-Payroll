<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendancePunctuality;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    private const MAX_GPS_ACCURACY_METERS = 50;

    public function clockIn(StoreAttendanceRequest $request): RedirectResponse
    {
        $user = $request->user();

        $attendance = Attendance::firstOrNew([
            'user_id' => $user->id,
            'tanggal' => today(),
        ]);

        if ($attendance->exists && $attendance->clock_in_time) {
            throw ValidationException::withMessages([
                'photo' => 'Anda sudah absen masuk hari ini.',
            ]);
        }

        $this->assertGpsAccuracyIsUsable($request->float('accuracy'));

        $distance = $this->assertWithinOfficeRadius(
            $user,
            $request->float('latitude'),
            $request->float('longitude'),
        );

        $workSchedule = $this->workSchedule($user);

        $clockIn = now();
        $punctuality = (new AttendancePunctuality)->evaluate($clockIn, $workSchedule->jam_masuk);

        $attendance->fill([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_time' => $clockIn,
            'clock_in_latitude' => $request->float('latitude'),
            'clock_in_longitude' => $request->float('longitude'),
            'clock_in_accuracy_meter' => (int) round($request->float('accuracy')),
            'clock_in_distance_meter' => $distance,
            'clock_in_photo_path' => $this->storePhoto($request, $user, 'masuk'),
            'clock_in_status' => $punctuality['status'],
            'clock_in_late_minutes' => $punctuality['minutes'],
        ]);

        try {
            $attendance->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'photo' => 'Anda sudah absen masuk hari ini.',
            ]);
        }

        return back();
    }

    public function clockOut(StoreAttendanceRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            // Locked so a second near-simultaneous clock-out (double tap,
            // network retry) waits for this one to finish instead of
            // silently overwriting it — clock-out updates an existing row,
            // so unlike clock-in there's no unique-constraint to catch it.
            $attendance = Attendance::where('user_id', $user->id)
                ->where('tanggal', today())
                ->lockForUpdate()
                ->first();

            if (! $attendance || ! $attendance->clock_in_time) {
                throw ValidationException::withMessages([
                    'photo' => 'Anda belum absen masuk hari ini.',
                ]);
            }

            if ($attendance->clock_out_time) {
                throw ValidationException::withMessages([
                    'photo' => 'Anda sudah absen pulang hari ini.',
                ]);
            }

            $this->assertGpsAccuracyIsUsable($request->float('accuracy'));

            $distance = $this->assertWithinOfficeRadius(
                $user,
                $request->float('latitude'),
                $request->float('longitude'),
            );

            $scheduledClockOut = today()->setTimeFromTimeString($this->workSchedule($user)->jam_pulang);
            $isEarly = now()->lessThan($scheduledClockOut);

            $attendance->fill([
                'clock_out_time' => now(),
                'clock_out_latitude' => $request->float('latitude'),
                'clock_out_longitude' => $request->float('longitude'),
                'clock_out_accuracy_meter' => (int) round($request->float('accuracy')),
                'clock_out_distance_meter' => $distance,
                'clock_out_photo_path' => $this->storePhoto($request, $user, 'pulang'),
                'clock_out_status' => $isEarly ? 'pulang_cepat' : 'tepat_waktu',
                'clock_out_early_minutes' => $isEarly ? abs($scheduledClockOut->diffInMinutes(now())) : null,
            ])->save();
        });

        return back();
    }

    private function assertGpsAccuracyIsUsable(float $accuracyMeters): void
    {
        if ($accuracyMeters > self::MAX_GPS_ACCURACY_METERS) {
            throw ValidationException::withMessages([
                'accuracy' => sprintf(
                    'Akurasi GPS terlalu rendah (±%dm). Coba lagi di area terbuka.',
                    round($accuracyMeters),
                ),
            ]);
        }
    }

    private function assertWithinOfficeRadius(User $user, float $latitude, float $longitude): int
    {
        $officeLocations = $user->company->officeLocations;

        abort_if($officeLocations->isEmpty(), 500, 'Lokasi kantor belum diatur untuk instansi ini.');

        $nearest = $officeLocations
            ->map(fn (OfficeLocation $office) => [
                'distance' => $office->distanceInMetersFrom($latitude, $longitude),
                'radius' => $office->radius_meter,
            ])
            ->sortBy('distance')
            ->first();

        if ($nearest['distance'] > $nearest['radius']) {
            throw ValidationException::withMessages([
                'latitude' => sprintf(
                    'Anda berada sekitar %dm dari kantor, di luar radius %dm yang diizinkan.',
                    round($nearest['distance']),
                    $nearest['radius'],
                ),
            ]);
        }

        return (int) round($nearest['distance']);
    }

    private function workSchedule(User $user): WorkSchedule
    {
        $workSchedule = $user->company->workSchedules()->first();

        abort_if($workSchedule === null, 500, 'Jam kerja belum diatur untuk instansi ini.');

        return $workSchedule;
    }

    private function storePhoto(StoreAttendanceRequest $request, User $user, string $type): string
    {
        $path = $request->file('photo')->storeAs(
            $user->id.'/'.today()->toDateString(),
            $type.'.'.$request->file('photo')->extension(),
            'attendance_photos',
        );

        abort_if($path === false, 500, 'Gagal menyimpan foto absen. Coba lagi.');

        return $path;
    }
}
