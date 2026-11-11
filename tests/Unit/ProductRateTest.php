<?php

namespace Tests\Unit;

use App\Modules\Catalog\Models\ProductRate;
use PHPUnit\Framework\TestCase;

class ProductRateTest extends TestCase
{
    public function test_scheme_bonus_is_pro_rata_and_never_for_returns(): void
    {
        $rate = new ProductRate(['bonus_buy' => 10, 'bonus_free' => 2]);

        $this->assertSame(2, $rate->schemeBonus(10));
        $this->assertSame(1, $rate->schemeBonus(5));
        $this->assertSame(0, $rate->schemeBonus(4));
        $this->assertSame(0, $rate->schemeBonus(-10));
    }
}
