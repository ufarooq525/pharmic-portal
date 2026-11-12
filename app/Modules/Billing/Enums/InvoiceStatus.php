<?php

namespace App\Modules\Billing\Enums;

use App\Support\Enums\HasOptions;

/** An issued invoice is locked; a cancelled one is reversed by a credit note. */
enum InvoiceStatus: string
{
    use HasOptions;

    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Issued => 'ok',
            self::Cancelled => 'mute',
        };
    }
}
