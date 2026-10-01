<?php

namespace App\Services;

use App\Models\LeaveRequestSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class LeaveRequestService
{
    /**
     * Atomically generates the next "IZN-{year}-{6 digit}" request number
     * for the current year.
     */
    public function generateRequestNumber(): string
    {
        $year = (int) now()->year;

        try {
            LeaveRequestSequence::firstOrCreate(['year' => $year], ['last_sequence' => 0]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request created this year's row first — fine,
            // the row exists either way by the time we reach the lock below.
        }

        return DB::transaction(function () use ($year) {
            $sequence = LeaveRequestSequence::where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_sequence++;
            $sequence->save();

            return sprintf('IZN-%d-%06d', $year, $sequence->last_sequence);
        });
    }
}
