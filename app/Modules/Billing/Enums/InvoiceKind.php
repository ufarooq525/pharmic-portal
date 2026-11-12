<?php

namespace App\Modules\Billing\Enums;

use App\Support\Enums\HasOptions;

/** An invoice, or the credit note that reverses one. */
enum InvoiceKind: string
{
    use HasOptions;

    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::CreditNote => 'Credit note',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Invoice => 'ok',
            self::CreditNote => 'info',
        };
    }

    /** Number prefix: INV-2610-0001, CN-2610-0001. */
    public function prefix(): string
    {
        return match ($this) {
            self::Invoice => 'INV',
            self::CreditNote => 'CN',
        };
    }
}
