<?php

namespace App\Support;

use App\Modules\Imports\Models\Import;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The month every page works on. Taken from ?period=YYYY-MM, otherwise the latest month that has an
 * import, otherwise the current month. Always the first day of the month.
 */
final class Period
{
    public static function fromRequest(Request $request): CarbonImmutable
    {
        $value = $request->query('period');

        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return CarbonImmutable::createFromFormat('!Y-m', $value);
        }

        return self::latest();
    }

    public static function latest(): CarbonImmutable
    {
        // Several parts of one page ask for this (page, sidebar, filters); look it up once per request.
        $attributes = request()->attributes;
        if (! $attributes->has('period.latest')) {
            $attributes->set('period.latest', Import::query()->max('period'));
        }
        $latest = $attributes->get('period.latest');

        // Month-end work is for the month that just ended, so with no files yet the default is last month.
        return $latest ? CarbonImmutable::parse($latest)->startOfMonth() : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }

    /** Bindings for SQL range checks that work the same on MySQL, PostgreSQL and SQLite. */
    public static function start(CarbonImmutable $period): string
    {
        return $period->startOfMonth()->startOfDay()->toDateTimeString();
    }

    public static function end(CarbonImmutable $period): string
    {
        return $period->endOfMonth()->endOfDay()->toDateTimeString();
    }
}
