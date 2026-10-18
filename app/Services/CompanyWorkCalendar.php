<?php

namespace App\Services;

use App\Models\CompanyCalendarDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Resolves the effective work/rest classification for a company's date. */
class CompanyWorkCalendar
{
    /**
     * $userId lets a calendar override that names specific employees (see
     * CompanyCalendarDay::$allowed_user_ids) resolve correctly per person.
     * That list means something different depending on the row's type:
     *  - type="workday": exclusively opens the date for the named people
     *    only — everyone else sees the date exactly as if the override
     *    didn't exist for them (e.g. a normal Saturday, or Sunday-closed).
     *  - type="holiday"/"rest_day": exempts the named people from the
     *    closure — they can still clock in — while everyone else stays
     *    closed as the holiday/rest day intends (e.g. a holiday range
     *    where one team still has to work a shift).
     * Omitting $userId only makes sense for a company-wide question that
     * isn't about any one employee's attendance.
     */
    public function dayType(int $companyId, CarbonInterface|string $date, ?int $userId = null): string
    {
        $dateString = $date instanceof CarbonInterface ? $date->toDateString() : $date;
        $override = CompanyCalendarDay::where('company_id', $companyId)
            ->whereDate('date', $dateString)
            ->first();

        if ($override === null) {
            return $this->defaultDayType($dateString);
        }

        $isNamed = $userId !== null && ! empty($override->allowed_user_ids)
            && in_array($userId, $override->allowed_user_ids, true);

        if ($override->type === 'workday') {
            if (! empty($override->allowed_user_ids) && ! $isNamed) {
                return $this->defaultDayType($dateString);
            }

            return 'workday';
        }

        // holiday / rest_day — a named person is exempted and can work;
        // everyone else stays closed for this date as normal.
        return $isNamed ? 'workday' : $override->type;
    }

    public function isWorkday(int $companyId, CarbonInterface|string $date, ?int $userId = null): bool
    {
        return $this->dayType($companyId, $date, $userId) === 'workday';
    }

    private function defaultDayType(string $dateString): string
    {
        return CarbonImmutable::parse($dateString)->isSunday() ? 'rest_day' : 'workday';
    }

    public function label(string $dayType): string
    {
        return match ($dayType) {
            'holiday' => 'Hari libur',
            'rest_day' => 'Hari istirahat',
            default => 'Hari kerja',
        };
    }
}
