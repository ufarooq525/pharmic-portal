<?php

namespace App\Modules\Imports\Bonus;

/**
 * Shares one product's bonus total out across the customer lines that bought it, in whole units.
 * Pure: no database. The shares always add up to the total when there is any line to give it to.
 *
 * - A positive total goes to lines with units > 0, in proportion to their weight (the scheme bonus);
 *   when no line has any weight, in proportion to units. Returns and zero lines get nothing.
 * - A negative total (more bonus came back than went out) goes to the return lines, by units returned.
 * - Rounding: each line gets the whole part of its exact share; the units left over go one each to
 *   the largest fractions (ties: more units first, then file order).
 */
final class BonusAllocator
{
    /**
     * @param  array<int|string, array{units: float, weight: float}>  $lines
     * @return array<int|string, int> bonus per line key
     */
    public static function allocate(array $lines, float $total): array
    {
        $out = array_fill_keys(array_keys($lines), 0);
        $target = (int) round($total);

        if ($target === 0) {
            return $out;
        }

        $sign = $target > 0 ? 1 : -1;
        $eligible = array_filter($lines, fn ($l) => $sign > 0 ? $l['units'] > 0 : $l['units'] < 0);

        if ($eligible === []) {
            return $out;
        }

        $weights = $sign > 0 ? array_map(fn ($l) => max(0.0, (float) $l['weight']), $eligible) : array_map(fn ($l) => abs($l['units']), $eligible);
        if (array_sum($weights) <= 0) {
            $weights = array_map(fn ($l) => abs($l['units']), $eligible);
        }

        $sum = array_sum($weights);
        $amount = abs($target);
        $fractions = [];
        $order = 0;

        foreach ($weights as $key => $weight) {
            $exact = $amount * $weight / $sum;
            $out[$key] = (int) floor($exact + 1e-9);
            $fractions[] = ['key' => $key, 'fraction' => $exact - $out[$key], 'units' => abs($eligible[$key]['units']), 'order' => $order++];
        }

        usort($fractions, fn ($a, $b) => [$b['fraction'], $b['units'], $a['order']] <=> [$a['fraction'], $a['units'], $b['order']]);

        $left = $amount - array_sum(array_map(fn ($f) => $out[$f['key']], $fractions));
        foreach (array_slice($fractions, 0, max(0, $left)) as $f) {
            $out[$f['key']]++;
        }

        return $sign > 0 ? $out : array_map(fn ($v) => -$v, $out);
    }
}
