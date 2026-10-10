<?php

namespace App\Http\Controllers;

use App\Models\FaceVerificationReceipt;
use App\Services\FaceDescriptorExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class FaceVerificationController extends Controller
{
    public function __construct(
        private readonly FaceDescriptorExtractor $faceExtractor,
    ) {}

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'action' => ['required', 'string', 'in:masuk,pulang'],
            'attempt_id' => ['required', 'string', 'max:64'],
            'sequence' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $checkedAt = now();

        $path = $request->file('photo')->store('face-verification-tmp');
        abort_if($path === false, 500, 'Gagal menyimpan foto.');

        try {
            $imageHash = hash_file('sha256', Storage::path($path)) ?: null;
            $result = $this->faceExtractor->extract(Storage::path($path));
        } finally {
            Storage::delete($path);
        }

        if (! $result->ok) {
            
            if (in_array($result->error, ['process_failed', 'invalid_output'], true)) {
                return response()->json([
                    'status' => 'service_unavailable',
                    'message' => 'Layanan verifikasi wajah sedang tidak tersedia. Coba lagi sebentar.',
                    'attempt_id' => $validated['attempt_id'],
                    'sequence' => $validated['sequence'],
                    'checked_at' => $checkedAt->toIso8601String(),
                    'expires_at' => null,
                ], 503);
            }

            return response()->json([
                'status' => $result->error,
                'message' => match ($result->error) {
                    'multiple_faces' => 'Terdeteksi lebih dari satu wajah.',
                    default => 'Wajah tidak terdeteksi.',
                },
                'attempt_id' => $validated['attempt_id'],
                'sequence' => $validated['sequence'],
                'checked_at' => $checkedAt->toIso8601String(),
                'expires_at' => null,
            ]);
        }

        $matches = $this->faceExtractor->matches($user->face_descriptor, $result->descriptor);

        if (! $matches) {
            // The live preview polls every ~2s, so this fires often during
            // normal use (still framing up) — kept at notice level, but the
            // distance is the one thing that tells a real "always rejects"
            // report apart from ordinary in-progress alignment.
            Log::notice('Live face preview did not match the enrolled face.', [
                'user_id' => $user->id,
                'action' => $validated['action'],
                'distance' => $this->faceExtractor->distance($user->face_descriptor, $result->descriptor),
            ]);

            return response()->json([
                'status' => 'mismatch',
                'message' => 'Wajah tidak cocok dengan yang terdaftar.',
                'attempt_id' => $validated['attempt_id'],
                'sequence' => $validated['sequence'],
                'checked_at' => $checkedAt->toIso8601String(),
                'expires_at' => null,
            ]);
        }

        $receipt = FaceVerificationReceipt::issue(
            $user,
            $request->session()->getId(),
            $validated['action'],
            $validated['attempt_id'],
            $imageHash,
            (int) config('face.receipt_ttl_seconds'),
        );

        return response()->json([
            'status' => 'matched',
            'message' => 'Wajah cocok.',
            'attempt_id' => $validated['attempt_id'],
            'sequence' => $validated['sequence'],
            'checked_at' => $checkedAt->toIso8601String(),
            'expires_at' => $receipt->expires_at->toIso8601String(),
            'receipt' => $receipt->token,
        ]);
    }
}
