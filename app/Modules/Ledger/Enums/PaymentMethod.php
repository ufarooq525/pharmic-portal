<?php

namespace App\Modules\Ledger\Enums;

use App\Support\Enums\HasOptions;

/** How a distributor paid. */
enum PaymentMethod: string
{
    use HasOptions;

    case Bank = 'bank';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank transfer',
            self::Cheque => 'Cheque',
            self::Cash => 'Cash',
            self::Online => 'Online / mobile wallet',
        };
    }
}
