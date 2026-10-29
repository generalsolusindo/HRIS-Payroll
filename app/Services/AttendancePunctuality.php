<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AttendancePunctuality
{
    /**
     * Grace periods do not change attendance classification.
     * Compared at whole-minute precision, matching what a clock-in device
     * displays (no seconds) — a clock-in anywhere within the scheduled
     * minute itself (e.g. 08:35:00-08:35:59 against a 08:35 deadline)
     * reads as on time; lateness is only counted from the next minute.
     *
     * @return array{status: string, minutes: ?int}
     */
    public function evaluate(CarbonInterface $clockIn, string $scheduledTime): array
    {
        $local = CarbonImmutable::instance($clockIn)->setTimezone('Asia/Jakarta')->startOfMinute();
        $deadline = $local->startOfDay()->setTimeFromTimeString($scheduledTime);

        if ($local->lessThanOrEqualTo($deadline)) {
            return ['status' => 'tepat_waktu', 'minutes' => null];
        }

        return [
            'status' => 'telat',
            'minutes' => (int) $deadline->diffInMinutes($local),
        ];
    }
}
