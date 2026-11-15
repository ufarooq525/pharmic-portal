<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Pagination for lists that are worked out in PHP (payroll, stock, ledger) rather than read
 * page by page from the database. Totals and KPIs still use the full list; only the table pages.
 */
final class Paginate
{
    public static function collection(iterable $items, int $perPage = 25, string $pageName = 'page', ?string $fragment = null): LengthAwarePaginator
    {
        $items = collect($items)->values();
        $last = max(1, (int) ceil($items->count() / $perPage));
        $page = min(LengthAwarePaginator::resolveCurrentPage($pageName), $last);

        $paginator = new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);

        $paginator->withQueryString();

        // fragment(null) would read the fragment instead of setting it.
        return $fragment === null ? $paginator : $paginator->fragment($fragment);
    }
}
