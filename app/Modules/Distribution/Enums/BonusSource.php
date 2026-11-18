<?php

namespace App\Modules\Distribution\Enums;

use App\Support\Enums\HasOptions;

/** Where a distributor’s bonus units come from. */
enum BonusSource: string
{
    use HasOptions;

    case File = 'file';
    case Scheme = 'scheme';
    // For files with no bonus column (the product grid): each product's bonus on the distributor's
    // Sales & Stock report is shared out across the customers who bought it.
    case Allocated = 'allocated';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Only the bonus printed in the file; no bonus when the file prints none',
            self::Scheme => 'Always use the product scheme, ignore the file',
            self::Allocated => 'Spread the bonus from the Sales & Stock report across customers',
        };
    }
}
