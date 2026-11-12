<?php

namespace App\Modules\Imports\Repositories;

use App\Modules\Distribution\Enums\BonusSource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
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

        $grouped = $rows->groupBy(fn ($r) => Customer::matchKey($r->customer_code, $r->customer_name, $r->area));
        $totals = ['units' => 0, 'bonus' => 0, 'value' => 0, 'sales_tax' => 0, 'withholding_tax' => 0];
        $customers = [];

        foreach ($import->customers as $c) {
            $key = Customer::matchKey($c['code'] ?? null, $c['name'], $c['area'] ?? null);
            $known = $register->get($key);
            $flags = [];
            $lines = [];
            $sum = ['units' => 0, 'bonus' => 0, 'value' => 0, 'sales_tax' => 0, 'withholding_tax' => 0];

            foreach ($grouped->get($key, collect()) as $row) {
                $product = $row->product_id ? $products->get($row->product_id) : null;
                $rate = $product?->rateOn($period);
                $scheme = $rate?->schemeBonus($row->units) ?? 0;
                $bonus = $distributor->bonusFor($row->bonus, $scheme);
                $value = $row->value ?? ($rate ? round($row->units * $rate->trade_price, 2) : 0);
                $st = $rate ? round($value * $rate->sales_tax_rate / 100, 2) : 0;
                $wht = $rate ? round($value * $rate->withholding_tax_rate / 100, 2) : 0;

                $note = null;
                if (! $product) {
                    $flags['unmapped'] = true;
                    $note = 'Product not linked yet';
                } elseif ($row->units < 0) {
                    $flags['return'] = true;
                    $note = 'Return, adds stock back';
                } elseif ($checkBonus && $row->bonus !== null && $row->units > 0 && (int) $row->bonus !== $scheme) {
                    $flags['bonus'] = true;
                    $note = 'Bonus '.fmt_num($row->bonus).' vs scheme '.$scheme;
                } elseif (! $distributor->bonusIsFromFile($row->bonus) && $bonus > 0) {
                    $note = "Bonus by scheme {$rate->bonus_buy}+{$rate->bonus_free}";
                }

                $lines[] = [
                    'code_in_file' => $row->product_code, 'name_in_file' => $row->product_name, 'product' => $product?->name, 'units' => $row->units, 'bonus' => $bonus,
                    'value' => $value, 'sales_tax_rate' => $rate?->sales_tax_rate, 'sales_tax' => $st,
                    'withholding_tax_rate' => $rate?->withholding_tax_rate, 'withholding_tax' => $wht, 'note' => $note,
                ];

                foreach (['units' => $row->units, 'bonus' => $bonus, 'value' => $value, 'sales_tax' => $st, 'withholding_tax' => $wht] as $k => $v) {
                    $sum[$k] += $v;
                }
            }

            foreach ($sum as $k => $v) {
                $totals[$k] += $v;
            }

            $reps = $known?->medicalReps ?? collect();
            [$status, $tone] = match (true) {
                isset($flags['unmapped']) => ['Link product', 'bad'],
                $known === null => ['New customer', 'info'],
                $reps->isEmpty() => ['Needs rep', 'warn'],
                $sum['units'] == 0 => ['No sale', 'mute'],
                isset($flags['return']) => ['Has return', 'info'],
                isset($flags['bonus']) => ['Bonus differs', 'warn'],
                default => ['Ready', 'ok'],
            };

            $customers[] = $sum + [
                'name' => $c['name'], 'code' => $c['code'] ?? null, 'area' => $c['area'] ?? null,
                'reps' => $reps->map(fn ($r) => $r->name.($r->pivot->share_percent < 100 ? " ({$r->pivot->share_percent}%)" : ''))->join(', '),
                'status' => $status, 'tone' => $tone, 'attention' => $tone !== 'ok', 'lines' => $lines,
            ];
        }

        return [
            'customers' => $customers,
            'totals' => $totals,
            'unmapped' => $rows->whereNull('product_id')->pluck('product_name')->unique()->values()->all(),
            'newCustomers' => collect($customers)->where('status', 'New customer')->count(),
        ];
    }
}
