<?php

/*
 * Number formatting used across the Blade views. Pakistani rupees, grouped thousands, no decimals
 * unless asked for.
 */

if (! function_exists('fmt_num')) {
    function fmt_num(float|int|string|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals);
    }
}

if (! function_exists('fmt_rs')) {
    function fmt_rs(float|int|string|null $value, int $decimals = 0): string
    {
        $value = (float) $value;

        return ($value < 0 ? '-' : '').'Rs '.number_format(abs($value), $decimals);
    }
}

if (! function_exists('fmt_pct')) {
    function fmt_pct(float|int|string|null $value, int $decimals = 1): string
    {
        return rtrim(rtrim(number_format((float) $value, $decimals), '0'), '.').'%';
    }
}

if (! function_exists('period_url')) {
    /** The current URL with a different ?period, keeping every other filter. */
    function period_url(string $period): string
    {
        return request()->fullUrlWithQuery(['period' => $period, 'page' => null]);
    }
}
