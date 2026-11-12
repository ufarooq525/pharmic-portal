<?php

namespace App\Modules\Sales\Enums;

use App\Support\Enums\HasOptions;

/** A posted receipt adds stock; a reverted one is kept for the record with its stock taken out. */
enum StockReceiptStatus: string
{
    use HasOptions;

    case Posted = 'posted';
    case Reverted = 'reverted';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'Posted',
            self::Reverted => 'Reverted',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Posted => 'ok',
            self::Reverted => 'mute',
        };
    }
}
