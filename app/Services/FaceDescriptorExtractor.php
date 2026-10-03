<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Wraps the node-face/ CLI, which runs @vladmandic/face-api in Node.js to
 * turn a photo into a 128-value face descriptor. Deliberately never trusts
 * a descriptor computed in the browser — this always re-derives it from
 * the actual uploaded photo, server-side.
 */
class FaceDescriptorExtractor
{
    public function extract(string $photoPath): FaceDescriptorResult
    {
        try {
            $result = Process::timeout((int) config('face.timeout_seconds'))
                ->run([
                    config('face.node_binary'),
                    config('face.script_path'),
                    $photoPath,
                ]);
        } catch (ProcessTimedOutException) {
            Log::warning('Face descriptor extraction timed out.', ['photo' => $photoPath]);

            return FaceDescriptorResult::failure('process_failed');
        }

        if (! $result->successful()) {
            Log::warning('Face descriptor extraction process failed.', [
                'photo' => $photoPath,
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
            ]);

            return FaceDescriptorResult::failure('process_failed');
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded) || ! array_key_exists('ok', $decoded)) {
            Log::warning('Face descriptor extraction returned unparseable output.', [
                'photo' => $photoPath,
                'output' => $result->output(),
            ]);

            return FaceDescriptorResult::failure('invalid_output');
        }

        if ($decoded['ok'] !== true) {
            return FaceDescriptorResult::failure(match ($decoded['error'] ?? null) {
                'no_face' => 'no_face',
                'multiple_faces' => 'multiple_faces',
                'invalid_input' => 'invalid_input',
                'load_failed' => 'load_failed',
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
