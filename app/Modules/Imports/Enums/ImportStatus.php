<?php

namespace App\Modules\Imports\Enums;

use App\Support\Enums\HasOptions;

/** Where an uploaded distributor file stands. Only confirmed files post sales and stock. */
enum ImportStatus: string
{
    use HasOptions;

    case NeedsReview = 'needs_review';
    case Blocked = 'blocked';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::NeedsReview => 'Needs review',
            self::Blocked => 'Wrong file',
            self::Confirmed => 'Confirmed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NeedsReview => 'warn',
            self::Blocked => 'bad',
            self::Confirmed => 'ok',
        };
    }
}
