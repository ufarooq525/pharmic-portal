<?php

namespace App\Modules\Distribution\Enums;

use App\Support\Enums\HasOptions;

/** Where a distributor’s bonus units come from. */
enum BonusSource: string
{
    use HasOptions;

    case File = 'file';
    case Scheme = 'scheme';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Use the bonus printed in the file; the product scheme where none is printed',
            self::Scheme => 'Always use the product scheme, ignore the file',
            self::None => 'Only the bonus printed in the file; no scheme bonus',
        };
    }
}
