@php
    $asOf = $period->endOfMonth();
@endphp
<x-layouts.app title="Master data · Products & taxes" description="Products with their prices, sales tax and withholding tax rates.">
    <x-masters-tabs current="products" />

    <section class="card flex flex-col gap-4" aria-labelledby="p-h">
        <h2 id="p-h" class="card-title">Products, prices and taxes for {{ $period->format('F Y') }}</h2>
        <p class="m-0 text-[13px] text-muted">Each product carries its own prices, sales tax rate and withholding tax rate. A change is saved with the date it starts, so earlier months keep their own rates.</p>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Product</th><th scope="col" class="r">Ex-FP</th><th scope="col" class="r">Trade price</th><th scope="col" class="r">Cost</th>
                    <th scope="col" class="r">Sales tax</th><th scope="col" class="r">WHT</th><th scope="col" class="r">Incentive</th><th scope="col" class="r">Bonus scheme</th>
                    <th scope="col">From</th><th scope="col">Names in distributor files</th>@if ($canEdit)<th scope="col"><span class="sr-only">Change</span></th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($products as $product)
                    @php($rate = $product->rateOn($asOf))
                    <tr>
                        <td class="font-bold">{{ $product->name }}</td>
                        @if ($rate)
                            <td class="r">{{ fmt_num($rate->exfp_price, 2) }}</td>
                            <td class="r">{{ fmt_num($rate->trade_price, 2) }}</td>
                            <td class="r">{{ fmt_num($rate->cost_price, 2) }}</td>
                            <td class="r">{{ fmt_pct($rate->sales_tax_rate, 2) }}</td>
                            <td class="r">{{ fmt_pct($rate->withholding_tax_rate, 2) }}</td>
                            <td class="r">{{ fmt_pct($rate->incentive_rate, 2) }}</td>
                            <td class="r">{{ $rate->bonus_buy ? $rate->bonus_buy.' + '.$rate->bonus_free : 'None' }}</td>
                            <td class="whitespace-nowrap">{{ $rate->effective_from->format('j M Y') }}</td>
                        @else
                            <td colspan="8"><x-chip tone="bad">No rate set for this month</x-chip></td>
                        @endif
                        <td class="text-[12px] text-muted">{{ $product->aliases->map(fn ($a) => $a->name_in_file)->unique()->take(4)->join(' · ') ?: '–' }}</td>
                        @if ($canEdit)
                            <td><a href="{{ request()->fullUrlWithQuery(['edit' => $product->id]) }}#rate-form" data-drawer="#rate-form" class="btn btn-sm">Change</a></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-[12px] text-muted">Sales tax and withholding tax are each worked out on the trade-price value of every sale line, at the rate in force for that month, and stored with the sale.</p>
    </section>

    @if ($canEdit && ($product = $products->firstWhere('id', $editing)))
        @php($rate = $product->rateOn($asOf))
        <section id="rate-form" class="card flex flex-col gap-4" aria-labelledby="r-h" data-drawer-source>
            <h2 id="r-h" class="card-title">New rates for {{ $product->name }}</h2>
            <form method="post" action="{{ route('products.rates', ['product' => $product, 'period' => $period->format('Y-m')]) }}" class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @csrf
                @foreach ([
                    ['effective_from', 'Starts on', 'date', now()->startOfMonth()->toDateString()],
                    ['exfp_price', 'Ex-FP price', 'number', $rate?->exfp_price],
                    ['trade_price', 'Trade price', 'number', $rate?->trade_price],
                    ['cost_price', 'Cost price', 'number', $rate?->cost_price],
                    ['sales_tax_rate', 'Sales tax %', 'number', $rate?->sales_tax_rate],
                    ['withholding_tax_rate', 'Withholding tax %', 'number', $rate?->withholding_tax_rate],
                    ['incentive_rate', 'Incentive %', 'number', $rate?->incentive_rate],
                    ['bonus_buy', 'Bonus: buy', 'number', $rate?->bonus_buy],
                    ['bonus_free', 'Bonus: free', 'number', $rate?->bonus_free],
                ] as [$name, $label, $type, $value])
                    <div class="flex flex-col gap-1.5">
                        <label for="{{ $name }}" class="label">{{ $label }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($type === 'number') step="0.01" min="0" @endif value="{{ old($name, $value) }}" required class="field">
                    </div>
                @endforeach
                <div class="flex items-end gap-2">
                    <button class="btn btn-primary">Save rates</button>
                    <button type="button" class="btn" data-drawer-close>Cancel</button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.app>
