<?php

namespace App\Modules\Sales\Enums;

use App\Support\Enums\HasOptions;

/** Why stock moved. Units are positive in, negative out. */
enum StockMovementType: string
{
    use HasOptions;

    case Opening = 'opening';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Purchase => 'Stock received',
            self::Sale => 'Sold + bonus',
            self::Return => 'Return',
            self::Adjustment => 'Adjustment',
        };
    }
}
