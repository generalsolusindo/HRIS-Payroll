<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\FaceVerificationReceipt;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendancePunctuality;
use App\Services\FaceDescriptorExtractor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    private const MAX_GPS_ACCURACY_METERS = 50;

    public function __construct(
        private readonly FaceDescriptorExtractor $faceExtractor,
    ) {}

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

        $this->assertGpsAccuracyIsUsable($user, $request->float('accuracy'));

        $distance = $this->assertWithinOfficeRadius(
            $user,
            $request->float('latitude'),
            $request->float('longitude'),
            $request->float('accuracy'),
        );

        $workSchedule = $this->workSchedule($user);

        $clockIn = now();
        $punctuality = (new AttendancePunctuality)->evaluate($clockIn, $workSchedule->jam_masuk);
        $photoPath = $this->storePhoto($request, $user, 'masuk');
        $face = $this->assertFaceMatches($user, 'attendance_photos', $photoPath);
        $this->consumeReceiptIfProvided($request, $user, 'masuk');

        $attendance->fill([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_time' => $clockIn,
            'clock_in_latitude' => $request->float('latitude'),
            'clock_in_longitude' => $request->float('longitude'),
            'clock_in_accuracy_meter' => (int) round($request->float('accuracy')),
            'clock_in_distance_meter' => $distance,
            'clock_in_photo_path' => $photoPath,
            'clock_in_status' => $punctuality['status'],
            'clock_in_late_minutes' => $punctuality['minutes'],
            'clock_in_face_match' => $face['match'],
            'clock_in_face_distance' => $face['distance'],
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

            $this->assertGpsAccuracyIsUsable($user, $request->float('accuracy'));

            $distance = $this->assertWithinOfficeRadius(
                $user,
                $request->float('latitude'),
                $request->float('longitude'),
                $request->float('accuracy'),
            );

            $scheduledClockOut = today()->setTimeFromTimeString($this->workSchedule($user)->jam_pulang);
            $clockOut = now();
            $isEarly = $clockOut->lessThan($scheduledClockOut);
            $photoPath = $this->storePhoto($request, $user, 'pulang');
            $face = $this->assertFaceMatches($user, 'attendance_photos', $photoPath);
            $this->consumeReceiptIfProvided($request, $user, 'pulang');

            $attendance->fill([
                'clock_out_time' => $clockOut,
                'clock_out_latitude' => $request->float('latitude'),
                'clock_out_longitude' => $request->float('longitude'),
                'clock_out_accuracy_meter' => (int) round($request->float('accuracy')),
                'clock_out_distance_meter' => $distance,
                'clock_out_photo_path' => $photoPath,
                'clock_out_status' => $isEarly ? 'pulang_cepat' : 'tepat_waktu',
                'clock_out_early_minutes' => $isEarly ? max(1, (int) $clockOut->diffInMinutes($scheduledClockOut)) : null,
                'clock_out_face_match' => $face['match'],
                'clock_out_face_distance' => $face['distance'],
            ])->save();
        });

        return back();
    }

    private function assertGpsAccuracyIsUsable(User $user, float $accuracyMeters): void
    {
        if ($accuracyMeters > self::MAX_GPS_ACCURACY_METERS) {
            Log::notice('Attendance location rejected.', ['user_id' => $user->id, 'reason' => 'accuracy', 'accuracy_meters' => $accuracyMeters]);
            throw ValidationException::withMessages([
                'accuracy' => sprintf(
                    'Akurasi GPS terlalu rendah (±%dm). Coba lagi di area terbuka.',
                    round($accuracyMeters),
                ),
            ]);
        }
    }

    private function assertWithinOfficeRadius(User $user, float $latitude, float $longitude, float $accuracy): int
    {
        $officeLocations = $user->company->officeLocations;

        abort_if($officeLocations->isEmpty(), 500, 'Lokasi kantor belum diatur untuk instansi ini.');

        $candidates = $officeLocations
            ->map(fn (OfficeLocation $office) => [
                'id' => $office->id,
                'distance' => $office->distanceInMetersFrom($latitude, $longitude),
                'radius' => min($office->radius_meter, OfficeLocation::MAX_ATTENDANCE_RADIUS_METERS),
            ])
            ->sortBy('distance');

        // Any configured office may contain the employee, even if a closer
        // office has a smaller radius that does not contain them.
        $inside = $candidates->first(fn (array $office) => $office['distance'] <= $office['radius']);
        if ($inside !== null) {
            return (int) round($inside['distance']);
        }
        $nearest = $candidates->first();

        if ($nearest['distance'] > $nearest['radius']) {
            Log::notice('Attendance location rejected.', [
                'user_id' => $user->id, 'reason' => 'outside_radius',
                'office_id' => $nearest['id'], 'distance_meters' => (int) round($nearest['distance']),
                'radius_meters' => $nearest['radius'], 'accuracy_meters' => $accuracy,
            ]);
            throw ValidationException::withMessages([
                'latitude' => sprintf(
                    'Titik lokasi perangkat terbaca %dm dari kantor (radius %dm; akurasi ±%dm). Jika Anda di kantor, coba dekat jendela/pintu dan periksa lokasi presisi. Hubungi HRD bila tetap gagal.',
                    round($nearest['distance']),
                    $nearest['radius'],
                    round($accuracy),
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

    /**
     * Face enrollment is required. A mismatch — or a
     * selfie the model can't read a face from at all — rejects the whole
     * attempt the same way an out-of-radius GPS reading does: nothing is
     * saved, and the employee has to retake the photo.
     *
     * @return array{match: bool|null, distance: string|null}
     */
    private function assertFaceMatches(User $user, string $disk, string $photoPath): array
    {
        if (! $user->hasFaceEnrolled()) {
            throw ValidationException::withMessages([
                'photo' => 'Daftarkan wajah Anda terlebih dahulu sebelum melakukan absensi.',
            ]);
        }

        $result = $this->faceExtractor->extract(Storage::disk($disk)->path($photoPath));

        if (! $result->ok) {
            Log::notice('Attendance face verification could not run.', [
                'user_id' => $user->id,
                'reason' => $result->error,
            ]);

            Storage::disk($disk)->delete($photoPath);

            throw ValidationException::withMessages([
                'photo' => 'Wajah tidak terdeteksi dengan jelas di foto. Pastikan wajah Anda terlihat penuh dan coba lagi.',
            ]);
        }

        $distance = $this->faceExtractor->distance($user->face_descriptor, $result->descriptor);
        $matches = $this->faceExtractor->matches($user->face_descriptor, $result->descriptor);

        if (! $matches) {
            Log::notice('Attendance face verification rejected — did not match the enrolled face.', [
                'user_id' => $user->id,
                'distance' => $distance,
            ]);

            Storage::disk($disk)->delete($photoPath);

            throw ValidationException::withMessages([
                'photo' => 'Wajah pada foto tidak cocok dengan wajah yang terdaftar. Coba lagi, atau hubungi HRD jika masalah berlanjut.',
            ]);
        }

        return ['match' => true, 'distance' => (string) $distance];
    }

    /**
     * Consumes a live-preview receipt if one was supplied — purely an
     * audit trail that a preview check happened. Never authoritative:
     * assertFaceMatches() above always independently re-extracts and
     * compares the actual submitted photo regardless of whether a
     * receipt was present, valid, or missing entirely (the frontend
     * live-preview is a later addition, so requests without one must
     * keep working exactly as before).
     */
    private function consumeReceiptIfProvided(StoreAttendanceRequest $request, User $user, string $action): void
    {
        $token = trim((string) $request->input('receipt', ''));

        if ($token === '') {
            return;
        }

        DB::transaction(function () use ($token, $user, $request, $action) {
            $receipt = FaceVerificationReceipt::where('token', $token)->lockForUpdate()->first();

            if ($receipt === null) {
                Log::notice('Attendance submitted with an unknown face-verification receipt.', [
                    'user_id' => $user->id, 'action' => $action,
                ]);

                return;
            }

            if (! $receipt->isValidFor($user, $request->session()->getId(), $action)) {
                Log::notice('Attendance submitted with a stale or mismatched face-verification receipt.', [
                    'user_id' => $user->id, 'receipt_id' => $receipt->id, 'action' => $action,
                ]);

                return;
            }

            $receipt->forceFill(['consumed_at' => now()])->save();
        });
    }
}
