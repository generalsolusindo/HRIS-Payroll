<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AttendancePunctuality
{
    /**
     * Grace periods do not change attendance classification.
     * Display whole elapsed minutes, with a minimum of one for sub-minute lateness.
     *
     * @return array{status: string, minutes: ?int}
     */
    public function evaluate(CarbonInterface $clockIn, string $scheduledTime): array
    {
        $local = CarbonImmutable::instance($clockIn)->setTimezone('Asia/Jakarta');
        $deadline = $local->startOfDay()->setTimeFromTimeString($scheduledTime);

        if ($local->lessThanOrEqualTo($deadline)) {
            return ['status' => 'tepat_waktu', 'minutes' => null];
        }

        return [
            'status' => 'telat',
            'minutes' => max(1, (int) $deadline->diffInMinutes($local)),
        ];
    }
}
