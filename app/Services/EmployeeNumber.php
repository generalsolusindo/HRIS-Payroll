<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EmployeeNumber
{
    public static function next(): string
    {
        return DB::transaction(function (): string {
            // Atomic increment serializes allocations, including on SQLite.
            DB::table('employee_number_sequences')->where('id', 1)->increment('last_number');
            $number = (int) DB::table('employee_number_sequences')->where('id', 1)->value('last_number');
            while (DB::table('users')->where('employee_number', str_pad((string) $number, 4, '0', STR_PAD_LEFT))->exists()) {
                DB::table('employee_number_sequences')->where('id', 1)->increment('last_number');
                $number++;
            }

            return str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        }, 5);
    }
}
