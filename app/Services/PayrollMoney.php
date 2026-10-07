<?php

namespace App\Services;

use InvalidArgumentException;

/** Decimal arithmetic; round once per calculated line to two decimal places. */
class PayrollMoney
{
    /** @return numeric-string */
    public static function decimal(string $value, int $scale = 2): string
    {
        if (! is_numeric($value) || ! preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            throw new InvalidArgumentException('Nominal harus berupa angka desimal biasa.');
        }

        return bcround($value, $scale, \RoundingMode::HalfAwayFromZero);
    }

    /** @return numeric-string */
    public static function multiply(string $rate, int $quantity, int $divisor = 1): string
    {
        $product = bcmul(self::decimal($rate), (string) $quantity, 4);

        return self::decimal(bcdiv($product, (string) $divisor, 8));
    }
}
