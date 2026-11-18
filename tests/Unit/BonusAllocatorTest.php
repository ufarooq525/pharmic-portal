<?php

namespace Tests\Unit;

use App\Modules\Imports\Bonus\BonusAllocator;
use PHPUnit\Framework\TestCase;

class BonusAllocatorTest extends TestCase
{
    public function test_shares_follow_the_scheme_and_always_add_up_to_the_total(): void
    {
        // 50 bonus over lines whose scheme gives 18, 3, 3 and 0: the leftover units go to the largest fractions.
        $shares = BonusAllocator::allocate([
            'a' => ['units' => 60, 'weight' => 18], 'b' => ['units' => 10, 'weight' => 3],
            'c' => ['units' => 10, 'weight' => 3], 'd' => ['units' => 2, 'weight' => 0],
        ], 50);

        $this->assertSame(50, array_sum($shares));
        $this->assertSame(['a' => 38, 'b' => 6, 'c' => 6, 'd' => 0], $shares);
    }

    public function test_no_scheme_weights_falls_back_to_units(): void
    {
        $shares = BonusAllocator::allocate(['a' => ['units' => 30, 'weight' => 0], 'b' => ['units' => 10, 'weight' => 0]], 10);

        $this->assertSame(['a' => 8, 'b' => 2], $shares); // 7.5 / 2.5: the tie goes to the larger line
    }

    public function test_returns_and_zero_lines_get_no_share(): void
    {
        $shares = BonusAllocator::allocate(['sale' => ['units' => 10, 'weight' => 3], 'return' => ['units' => -2, 'weight' => 0], 'zero' => ['units' => 0, 'weight' => 0]], 3);

        $this->assertSame(['sale' => 3, 'return' => 0, 'zero' => 0], $shares);
    }

    public function test_a_negative_total_goes_to_the_returns(): void
    {
        $shares = BonusAllocator::allocate(['sale' => ['units' => 5, 'weight' => 1], 'r1' => ['units' => -6, 'weight' => 0], 'r2' => ['units' => -3, 'weight' => 0]], -3);

        $this->assertSame(['sale' => 0, 'r1' => -2, 'r2' => -1], $shares);
    }

    public function test_nothing_to_give_it_to_gives_nothing(): void
    {
        $this->assertSame(['r' => 0], BonusAllocator::allocate(['r' => ['units' => -4, 'weight' => 0]], 5));
        $this->assertSame([], BonusAllocator::allocate([], 5));
        $this->assertSame(['a' => 0], BonusAllocator::allocate(['a' => ['units' => 10, 'weight' => 3]], 0));
    }
}
