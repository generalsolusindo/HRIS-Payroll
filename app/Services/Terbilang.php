<?php

namespace App\Services;

/**
 * Converts a whole-rupiah integer amount into Indonesian words, for the
 * "Terbilang: ..." line on a printed payslip — the standard phrasing
 * Indonesian payroll/accounting documents use (e.g. "Satu Juta Empat
 * Ratus Dua Puluh Ribu Rupiah").
 */
class Terbilang
{
    private const ONES = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
        'Sepuluh', 'Sebelas',
    ];

    public static function rupiah(int $amount): string
    {
        if ($amount === 0) {
            return 'Nol Rupiah';
        }

        $negative = $amount < 0;
        $words = trim(self::words(abs($amount)));

        return ($negative ? 'Minus ' : '').$words.' Rupiah';
    }

    private static function words(int $number): string
    {
        if ($number < 12) {
            return self::ONES[$number];
        }

        if ($number < 20) {
            return trim(self::words($number - 10).' Belas');
        }

        if ($number < 100) {
            $tens = intdiv($number, 10);
            $remainder = $number % 10;

            return trim(self::words($tens).' Puluh '.self::words($remainder));
        }

        if ($number < 200) {
            return trim('Seratus '.self::words($number - 100));
        }

        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $remainder = $number % 100;

            return trim(self::words($hundreds).' Ratus '.self::words($remainder));
        }

        if ($number < 2000) {
            return trim('Seribu '.self::words($number - 1000));
        }

        if ($number < 1_000_000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;

            return trim(self::words($thousands).' Ribu '.self::words($remainder));
        }

        if ($number < 1_000_000_000) {
            $millions = intdiv($number, 1_000_000);
            $remainder = $number % 1_000_000;

            return trim(self::words($millions).' Juta '.self::words($remainder));
        }

        if ($number < 1_000_000_000_000) {
            $billions = intdiv($number, 1_000_000_000);
            $remainder = $number % 1_000_000_000;

            return trim(self::words($billions).' Miliar '.self::words($remainder));
        }

        $trillions = intdiv($number, 1_000_000_000_000);
        $remainder = $number % 1_000_000_000_000;

        return trim(self::words($trillions).' Triliun '.self::words($remainder));
    }
}
