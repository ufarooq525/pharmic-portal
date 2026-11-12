<?php

namespace App\Support\Enums;

/**
 * Shared helpers for backed enums shown in the UI. The enum provides label(); tone() (a chip
 * colour: ok, warn, bad, info, mute) is optional.
 */
trait HasOptions
{
    /** value => label, for select boxes. @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function tone(): string
    {
        return 'mute';
    }
}
