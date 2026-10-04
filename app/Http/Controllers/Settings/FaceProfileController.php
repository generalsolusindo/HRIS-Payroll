<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\FaceEnrollmentUpdatedMail;
use App\Services\FaceDescriptorExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FaceProfileController extends Controller
{
    public function __construct(
        private readonly FaceDescriptorExtractor $extractor,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/face', [
            'hasFaceEnrolled' => $user->hasFaceEnrolled(),
            'faceEnrolledAt' => $user->face_enrolled_at,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();

        // Only the extracted descriptor is kept — the enrollment photo
        // itself is deleted right after extraction, success or not, so we
        // don't retain more biometric material than the feature needs.
        $path = $request->file('photo')->store('face-enrollment-tmp');

        abort_if($path === false, 500, 'Gagal menyimpan foto. Coba lagi.');

        try {
            $result = $this->extractor->extract(Storage::path($path));
        } finally {
            Storage::delete($path);
        }

        if (! $result->ok) {
            throw ValidationException::withMessages([
                'photo' => match ($result->error) {
                    'no_face' => 'Wajah tidak terdeteksi di foto. Pastikan wajah terlihat jelas, lalu coba lagi.',
                    'multiple_faces' => 'Terdeteksi lebih dari satu wajah. Pastikan hanya wajah Anda yang terlihat di foto.',
                    default => 'Gagal memproses foto. Coba lagi.',
                },
            ]);
        }

        $wasAlreadyEnrolled = $user->hasFaceEnrolled();

        $user->face_descriptor = $result->descriptor;
        $user->face_enrolled_at = now();
        $user->save();

        try {
            Mail::to($user->email)->send(new FaceEnrollmentUpdatedMail($user, reEnrolled: $wasAlreadyEnrolled));
        } catch (Throwable $exception) {
            Log::warning('Failed to send face enrollment notification email.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wajah berhasil didaftarkan.']);

        return $wasAlreadyEnrolled ? back() : redirect()->route('dashboard');
    }
}
