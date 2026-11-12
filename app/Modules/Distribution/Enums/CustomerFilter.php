<?php

namespace App\Modules\Distribution\Enums;

use App\Support\Enums\HasOptions;

/** The filter pills on the Customers page. Customers are never deleted; they go quiet. */
enum CustomerFilter: string
{
    use HasOptions;

    case All = 'all';
    case Active = 'active';
    case New = 'new';
    case NeedsRep = 'needs_rep';
    case NoPurchase = 'no_purchase';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Active => 'Bought this month',
            self::New => 'New this month',
            self::NeedsRep => 'Needs a rep',
            self::NoPurchase => 'No purchase (1–2 months)',
            self::Inactive => 'Inactive (3+ months)',
        };
    }
}
