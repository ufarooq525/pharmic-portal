<?php

namespace App\Modules\Imports\Repositories;

use App\Modules\Distribution\Enums\BonusSource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Bonus\ImportBonus;
use App\Modules\Imports\Models\Import;
use Carbon\CarbonImmutable;

/**
 * What an import will post once confirmed, worked out with the month's own rates: bonus by scheme
 * where the file has no bonus column, value, sales tax and withholding tax per line, and a status
 * per customer so the reviewer sees what needs a decision.
 */
class ImportReviewRepository
{
    public function build(Import $import): array
    {
        $period = CarbonImmutable::parse($import->period)->endOfMonth();
        $distributor = $import->distributor;
        $checkBonus = $distributor->bonus_source === BonusSource::File;
        $rows = $import->rows()->get();
        $products = Product::with('rates')->findMany($rows->pluck('product_id')->filter()->unique())->keyBy('id');
        $register = Customer::where('distributor_id', $import->distributor_id)->with('medicalReps:id,name')->get()->keyBy('match_key');
        $bonuses = new ImportBonus($import, $rows, $products, $period);

        $grouped = $rows->groupBy(fn ($r) => Customer::matchKey($r->customer_code, $r->customer_name, $r->area));
        $totals = ['units' => 0, 'bonus' => 0, 'value' => 0, 'sales_tax' => 0, 'withholding_tax' => 0];
        $customers = [];

        foreach ($import->customers as $index => $c) {
            $key = Customer::matchKey($c['code'] ?? null, $c['name'], $c['area'] ?? null);
            $known = $register->get($key);
            $flags = [];
            $lines = [];
            $sum = ['units' => 0, 'bonus' => 0, 'value' => 0, 'sales_tax' => 0, 'withholding_tax' => 0];

            foreach ($grouped->get($key, collect()) as $row) {
                $product = $row->product_id ? $products->get($row->product_id) : null;
                $rate = $product?->rateOn($period);
                $decided = $bonuses->forRow($row);
                $scheme = $decided['scheme'];
                $bonus = $decided['bonus'];
                $value = $row->value ?? ($rate ? round($row->units * $rate->trade_price, 2) : 0);
                $st = $rate ? round($value * $rate->sales_tax_rate / 100, 2) : 0;
                $wht = $rate ? round($value * $rate->withholding_tax_rate / 100, 2) : 0;

                $note = null;
                if (! $product) {
                    $flags['unmapped'] = true;
                    $note = 'Product not linked yet';
                } elseif ($row->units < 0) {
                    $flags['return'] = true;
                    $note = 'Return: '.fmt_num(-$row->units).' units come back into stock';
                } elseif ($checkBonus && $row->bonus !== null && $row->units > 0 && (int) $row->bonus !== $scheme) {
                    $flags['bonus'] = true;
                    $note = 'Bonus '.fmt_num($row->bonus).' vs scheme '.$scheme;
                } elseif ($decided['rule'] === 'scheme' && $bonus > 0) {
                    $note = "Bonus by scheme {$rate->bonus_buy}+{$rate->bonus_free}";
                }

                // How the bonus is decided, in words, for checking against the PDF.
                $bonusWhy = $decided['why'];

                $lines[] = [
                    'code_in_file' => $row->product_code, 'name_in_file' => $row->product_name, 'product' => $product?->name, 'units' => $row->units, 'bonus' => $bonus,
                    'value' => $value, 'sales_tax_rate' => $rate?->sales_tax_rate, 'sales_tax' => $st,
                    'withholding_tax_rate' => $rate?->withholding_tax_rate, 'withholding_tax' => $wht, 'note' => $note,
                    'bonus_in_file' => $row->bonus, 'scheme_bonus' => $scheme, 'bonus_why' => $bonusWhy, 'bonus_estimated' => $decided['estimated'],
                    'source_page' => $row->source_page, 'source_text' => $row->source_text,
                ];

                foreach (['units' => $row->units, 'bonus' => $bonus, 'value' => $value, 'sales_tax' => $st, 'withholding_tax' => $wht] as $k => $v) {
                    $sum[$k] += $v;
                }
            }

            foreach ($sum as $k => $v) {
                $totals[$k] += $v;
            }

            // The customer's own total as printed in the file (where the layout prints one): the lines read must add up to it.
            $file = ['units' => $c['file_units'] ?? null, 'bonus' => $c['file_bonus'] ?? null, 'value' => $c['file_value'] ?? null];
            $readValue = array_sum(array_map(fn ($r) => (float) ($r->value ?? 0), $grouped->get($key, collect())->all()));
            $totalOk = $file['units'] === null ? null
                : abs($file['units'] - $sum['units']) < 0.01 && ($file['value'] === null || $import->layout?->value === 'matrix' || abs($file['value'] - $readValue) < 1);

            $reps = $known?->medicalReps ?? collect();
            [$status, $tone] = match (true) {
                $totalOk === false => ['Total differs', 'bad'],
                isset($flags['unmapped']) => ['Link product', 'bad'],
                $known === null => ['New customer', 'info'],
                $reps->isEmpty() => ['Needs rep', 'warn'],
                $sum['units'] == 0 => ['No sale', 'mute'],
                isset($flags['return']) => ['Has return', 'info'],
                isset($flags['bonus']) => ['Bonus differs', 'warn'],
                default => ['Ready', 'ok'],
            };

            $customers[] = $sum + [
                'index' => $index, 'name' => $c['name'], 'code' => $c['code'] ?? null, 'area' => $c['area'] ?? null,
                'reps' => $reps->map(fn ($r) => $r->name.($r->pivot->share_percent < 100 ? " ({$r->pivot->share_percent}%)" : ''))->join(', '),
                'status' => $status, 'tone' => $tone, 'attention' => $tone !== 'ok', 'lines' => $lines,
                'bonus_estimated' => collect($lines)->contains('bonus_estimated', true),
                'file_units' => $file['units'], 'file_bonus' => $file['bonus'], 'file_value' => $file['value'], 'read_value' => $readValue, 'total_ok' => $totalOk,
            ];
        }

        return [
            'customers' => $customers,
            'totals' => $totals,
            // Product names to link: from the customer file, and report products that match nothing yet.
            'unmapped' => $rows->whereNull('product_id')->pluck('product_name')->merge($bonuses->unmatchedReportNames())->unique()->values()->all(),
            'allocated' => $bonuses->isAllocated(),
            'reportProducts' => $bonuses->products(),
            'reportWarnings' => $bonuses->warnings(),
            'newCustomers' => collect($customers)->where('status', 'New customer')->count(),
        ];
    }
}
