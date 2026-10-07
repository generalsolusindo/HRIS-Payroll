<?php

namespace Tests\Unit;

use App\Services\PayrollMoney;
use PHPUnit\Framework\TestCase;

class PayrollMoneyTest extends TestCase
{
    public function test_money_is_not_converted_through_binary_floats(): void
    {
        $this->assertSame('9999999999999999.99', PayrollMoney::decimal('9999999999999999.99'));
        $this->assertSame('0.30', PayrollMoney::multiply('0.10', 3));
        $this->assertSame('416.67', PayrollMoney::multiply('25000.00', 1, 60));
        $this->assertSame('12500.00', PayrollMoney::multiply('25000.00', 30, 60));
        $this->assertSame('1.01', PayrollMoney::decimal('1.005'));
        $this->assertSame('-1.01', PayrollMoney::decimal('-1.005'));
    }

    public function test_non_decimal_input_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PayrollMoney::decimal('1e10');
    }
}
