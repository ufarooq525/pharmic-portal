<?php

namespace App\Modules\Imports\Http\Resources;

use Illuminate\Http\Request;

/** An import with its rows grouped per customer, the way the review screen shows them. */
class ImportDetailResource extends ImportResource
{
    public function toArray(Request $request): array
    {
        $rows = $this->rows()->with('product:id,name')->get()->groupBy(fn ($r) => ($r->customer_code ?: $r->customer_name).'|'.$r->area);

        return parent::toArray($request) + [
            'customers' => collect($this->customers)->map(function (array $c) use ($rows) {
                $lines = $rows->get((($c['code'] ?? null) ?: $c['name']).'|'.($c['area'] ?? ''), collect());

                return $c + [
                    'units' => $lines->sum('units'),
                    'value' => $lines->sum('value'),
                    'lines' => $lines->map(fn ($r) => [
                        'product_code' => $r->product_code, 'product_name' => $r->product_name, 'product' => $r->product?->name,
                        'units' => $r->units, 'bonus' => $r->bonus, 'trade_price' => $r->trade_price, 'value' => $r->value,
                    ])->values(),
                ];
            })->values(),
        ];
    }
}
