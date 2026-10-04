<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to node-face/server.js — a persistent local worker (127.0.0.1
 * only) that runs @vladmandic/face-api in Node.js and turns a photo into
 * a 128-value face descriptor. The worker loads its models once at
 * startup, so requests here are fast (~0.5s) instead of the ~1.7s a
 * fresh `node extract-descriptor.js` invocation costs per call — needed
 * once the frontend starts polling this every ~1-1.5s for a live
 * preview. Deliberately never trusts a descriptor computed in the
 * browser — this always re-derives it from the actual uploaded photo,
 * server-side.
 */
class FaceDescriptorExtractor
{
    public function extract(string $photoPath): FaceDescriptorResult
    {
        try {
            $response = Http::timeout((int) config('face.timeout_seconds'))
                ->withHeaders(array_filter([
                    'X-Worker-Secret' => config('face.worker_secret'),
                ]))
                ->post(rtrim((string) config('face.worker_url'), '/').'/extract', [
                    'path' => $photoPath,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Face worker unreachable.', [
                'photo' => $photoPath,
                'error' => $exception->getMessage(),
            ]);

            return FaceDescriptorResult::failure('process_failed');
        }

        if ($response->failed()) {
            Log::warning('Face worker request failed.', [
                'photo' => $photoPath,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return FaceDescriptorResult::failure('process_failed');
        }

        $decoded = $response->json();

        if (! is_array($decoded) || ! array_key_exists('ok', $decoded)) {
            Log::warning('Face worker returned unparseable output.', [
                'photo' => $photoPath,
                'output' => $response->body(),
            ]);

            return FaceDescriptorResult::failure('invalid_output');
        }

        if ($decoded['ok'] !== true) {
            return FaceDescriptorResult::failure(match ($decoded['error'] ?? null) {
                'no_face' => 'no_face',
                'multiple_faces' => 'multiple_faces',
                'invalid_input' => 'invalid_input',
                'load_failed' => 'load_failed',
                // The worker's internal queue/timeout guards — a process
                // problem, not a malformed-response problem.
                'queue_full', 'timeout' => 'process_failed',
                default => 'invalid_output',
            });
        }

        $descriptor = $decoded['descriptor'] ?? null;

        if (! is_array($descriptor) || count($descriptor) !== 128) {
            Log::warning('Face descriptor extraction returned a malformed descriptor.', ['photo' => $photoPath]);

            return FaceDescriptorResult::failure('invalid_output');
        }

        return FaceDescriptorResult::success(array_map(floatval(...), $descriptor));
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function distance(array $a, array $b): float
    {
        $sumOfSquares = 0.0;

        foreach ($a as $i => $value) {
            $sumOfSquares += ($value - $b[$i]) ** 2;
        }

        return sqrt($sumOfSquares);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function matches(array $a, array $b): bool
    {
        return $this->distance($a, $b) <= (float) config('face.match_threshold');
    }
}
