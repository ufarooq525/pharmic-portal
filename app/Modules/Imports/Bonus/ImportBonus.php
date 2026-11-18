<?php

namespace App\Modules\Imports\Bonus;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Enums\BonusSource;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Models\ImportRow;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The bonus each line of an import gets, and why. Used by the review page and by confirming, so what
 * is shown is what is posted.
 *
 * File / Scheme distributors: the printed bonus or the product scheme (Distributor::bonusFor).
 * Allocated distributors: each product's net bonus on the Sales & Stock report is shared out across
 * the lines of that product (BonusAllocator). A product the report doesn't list falls back to the
 * scheme, and so does the whole file while no report is attached. Differences between the two files
 * are warnings, never blocks.
 *
 * Report products are matched to the customer file's product names with spaces and punctuation
 * ignored ("GYNOFOL TAB" = "GYNOF OL TAB"), otherwise through the distributor's linked names or item codes.
 */
final class ImportBonus
{
    /** @var array<int, array{bonus: float, rule: string, scheme: int, target: ?float, why: string, estimated: bool}> */
    private array $lines = [];

    /** @var list<array{name: string, file_units: float, report_units: float, report_bonus: float, given: float, units_ok: bool, given_ok: bool}> */
    private array $products = [];

    /** @var list<string> report product names with sales that match nothing yet */
    private array $unmatched = [];

    private bool $allocated;

    /**
     * @param  Collection<int, ImportRow>  $rows
     * @param  Collection<int, Product>  $products  by id, with rates
     */
    public function __construct(private readonly Import $import, Collection $rows, Collection $products, CarbonInterface $periodEnd)
    {
        $distributor = $import->distributor;
        $this->allocated = $distributor->bonus_source === BonusSource::Allocated;
        $rates = [];
        $schemes = [];

        foreach ($rows as $row) {
            $rate = $row->product_id ? $products->get($row->product_id)?->rateOn($periodEnd) : null;
            $rates[$row->id] = $rate;
            $schemes[$row->id] = $rate?->schemeBonus($row->units) ?? 0;
        }

        $schemeText = fn (ImportRow $row) => $rates[$row->id] ? " {$rates[$row->id]->bonus_buy}+{$rates[$row->id]->bonus_free}" : '';

        if (! $this->allocated) {
            foreach ($rows as $row) {
                $rule = $distributor->bonusRule($row->bonus);
                $this->lines[$row->id] = [
                    'bonus' => $distributor->bonusFor($row->bonus, $schemes[$row->id]), 'rule' => $rule, 'scheme' => $schemes[$row->id], 'target' => null, 'estimated' => false,
                    'why' => match ($rule) {
                        'scheme' => 'Bonus from the scheme'.$schemeText($row).' (distributor set to always use the scheme)'.($row->bonus !== null ? '; the file printed '.fmt_num($row->bonus) : ''),
                        'none' => 'No bonus printed in the file, so no bonus',
                        default => 'Bonus as printed in the file: '.fmt_num($row->bonus),
                    },
                ];
            }

            return;
        }

        $fallback = function (ImportRow $row, string $reason) use ($schemes, $schemeText) {
            $this->lines[$row->id] = ['bonus' => (float) $schemes[$row->id], 'rule' => 'scheme', 'scheme' => $schemes[$row->id], 'target' => null, 'estimated' => false,
                'why' => 'Bonus from the scheme'.$schemeText($row).": {$reason}"];
        };

        if (! $import->hasStockReport()) {
            $rows->each(fn ($row) => $fallback($row, 'no Sales & Stock report attached yet'));

            return;
        }

        foreach ($this->groups($rows) as $group) {
            $groupRows = $group['rows'];
            $shares = BonusAllocator::allocate(
                $groupRows->mapWithKeys(fn ($r) => [$r->id => ['units' => (float) $r->units, 'weight' => (float) $schemes[$r->id]]])->all(),
                $group['report_bonus'],
            );
            $weighted = $groupRows->contains(fn ($r) => $r->units > 0 && $schemes[$r->id] > 0);

            foreach ($groupRows as $row) {
                $share = $shares[$row->id];
                $this->lines[$row->id] = [
                    'bonus' => (float) $share, 'rule' => 'allocated', 'scheme' => $schemes[$row->id], 'target' => $group['report_bonus'], 'estimated' => true,
                    'why' => 'Estimated: share of the '.fmt_num($group['report_bonus'])." bonus units “{$group['name']}” has on the Sales & Stock report"
                        .($row->units <= 0 && $group['report_bonus'] >= 0 ? ' (returns get no share)' : ($weighted ? ', split by the scheme'.$schemeText($row) : ', split by units')),
                ];
            }

            $fileUnits = (float) $groupRows->sum('units');
            $given = (float) array_sum($shares);
            $this->products[] = [
                'name' => $group['name'], 'file_units' => $fileUnits, 'report_units' => $group['report_units'], 'report_bonus' => $group['report_bonus'], 'given' => $given,
                'units_ok' => abs($fileUnits - $group['report_units']) < 0.01, 'given_ok' => abs($given - round($group['report_bonus'])) < 0.01,
            ];
        }

        // Lines of a product the report does not list.
        $leftover = $rows->reject(fn ($r) => isset($this->lines[$r->id]));
        foreach ($leftover->groupBy('product_name') as $name => $productRows) {
            $productRows->each(fn ($row) => $fallback($row, 'this product is not on the Sales & Stock report'));
            $units = (float) $productRows->sum('units');
            if ($units != 0) {
                $this->products[] = ['name' => $name, 'file_units' => $units, 'report_units' => 0.0, 'report_bonus' => 0.0,
                    'given' => (float) $productRows->sum(fn ($r) => $this->lines[$r->id]['bonus']), 'units_ok' => false, 'given_ok' => true];
            }
        }
    }

    /** @return array{bonus: float, rule: string, scheme: int, target: ?float, why: string, estimated: bool} */
    public function forRow(ImportRow $row): array
    {
        return $this->lines[$row->id];
    }

    /** One row per product: customer file vs Sales & Stock report (allocated distributors with a report). */
    public function products(): array
    {
        return $this->products;
    }

    /** Report products with sales that match no product of the customer file and no linked name: to be linked. */
    public function unmatchedReportNames(): array
    {
        return $this->unmatched;
    }

    public function isAllocated(): bool
    {
        return $this->allocated;
    }

    /**
     * Warnings about the Sales & Stock report, shown on the review page. None of them blocks confirming.
     *
     * @return list<array{key: string, ok: bool, message: string}>
     */
    public function warnings(): array
    {
        if (! $this->allocated) {
            return [];
        }

        if (! $this->import->hasStockReport()) {
            return [['key' => 'ssr_missing', 'ok' => false, 'message' => 'No Sales & Stock report yet: the bonus comes from the product scheme until it is uploaded.']];
        }

        $differ = array_filter($this->products, fn ($p) => ! $p['units_ok']);
        $unassigned = array_filter($this->products, fn ($p) => ! $p['given_ok']);

        return [
            ...($this->import->stock_report_checks ?? []),
            ['key' => 'ssr_units', 'ok' => $differ === [], 'message' => $differ === []
                ? 'Units match the Sales & Stock report for every product.'
                : 'Units differ from the Sales & Stock report: '.implode('; ', array_map(fn ($p) => "{$p['name']} ".fmt_num($p['file_units']).' in the customer file, '.fmt_num($p['report_units']).' on the report', $differ))
                    .'. The report’s bonus is still shared out.'],
            ...($unassigned === [] ? [] : [['key' => 'ssr_unassigned', 'ok' => false, 'message' => 'Bonus with no customer to give it to: '
                .implode('; ', array_map(fn ($p) => fmt_num($p['report_bonus'] - $p['given'])." of {$p['name']}", $unassigned)).'.']]),
        ];
    }

    /**
     * Report products with their matching customer-file lines.
     *
     * @return list<array{name: string, report_units: float, report_bonus: float, rows: Collection<int, ImportRow>}>
     */
    private function groups(Collection $rows): array
    {
        $squash = fn (string $s) => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($s));
        $byName = $rows->groupBy(fn ($r) => $squash($r->product_name));
        $aliases = $this->import->distributor->aliases()->get(['name_in_file', 'code_in_file', 'product_id']);
        $aliasByName = $aliases->mapWithKeys(fn ($a) => [$squash($a->name_in_file) => $a->product_id]);
        $aliasByCode = $aliases->whereNotNull('code_in_file')->pluck('product_id', 'code_in_file');

        $groups = [];
        $claimed = [];
        $later = [];

        // First pass: same name as a product column of the customer file.
        foreach ($this->import->stock_report['items'] ?? [] as $item) {
            $key = $squash($item['name']);

            if ($byName->has($key)) {
                $claimed[$key] = true;
                $groups['n:'.$key] = ['name' => $item['name'], 'report_units' => (float) $item['net_units'], 'report_bonus' => (float) $item['net_bonus'], 'rows' => $byName[$key]];
            } elseif ($item['net_units'] != 0 || $item['net_bonus'] != 0 || $item['sale_units'] != 0 || $item['return_units'] != 0) {
                $later[] = $item;
            }
        }

        // Second pass: spelled differently, matched through a linked name or the distributor's item code.
        foreach ($later as $item) {
            $productId = $aliasByName[$squash($item['name'])] ?? $aliasByCode[$item['code']] ?? null;

            if ($productId === null) {
                $this->unmatched[] = $item['name'];

                continue;
            }

            $key = 'p:'.$productId;
            $groups[$key] ??= ['name' => $item['name'], 'report_units' => 0.0, 'report_bonus' => 0.0,
                'rows' => $rows->filter(fn ($r) => (int) $r->product_id === (int) $productId && ! isset($claimed[$squash($r->product_name)]))->values()];
            $groups[$key]['report_units'] += (float) $item['net_units'];
            $groups[$key]['report_bonus'] += (float) $item['net_bonus'];
        }

        // Two report products can't both claim the same lines.
        $taken = [];
        foreach ($groups as $key => $group) {
            $groups[$key]['rows'] = $group['rows']->reject(fn ($r) => isset($taken[$r->id]))->values();
            $groups[$key]['rows']->each(function ($r) use (&$taken) {
                $taken[$r->id] = true;
            });
        }

        return array_values($groups);
    }
}
