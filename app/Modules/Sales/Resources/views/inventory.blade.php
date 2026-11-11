@php
    $sum = fn ($k) => $rows->sum($k);
    $levels = ['out' => ['Out of stock', 'bad'], 'low' => ['Reorder', 'warn'], 'ok' => ['OK', 'ok']];
@endphp
<x-layouts.app title="Inventory" description="Stock movement and closing stock for {{ $period->format('F Y') }}.">
    <x-page-header title="Inventory" sub="Stock for {{ $period->format('F Y') }}. Confirmed imports take out units sold plus bonus units; returns add stock back. Opening stock comes from the client’s Excel sheet." />

    <div class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3.5">
        <x-kpi label="Closing units" :value="fmt_num($sum('closing'))" note="all products" />
        <x-kpi label="Value at cost" :value="fmt_rs($sum('value_cost'))" note="closing stock" />
        <x-kpi label="Value at Ex-FP" :value="fmt_rs($sum('value_exfp'))" note="closing stock" />
        <x-kpi label="Need reordering" :value="fmt_num($rows->where('level', '!=', 'ok')->count())" note="under 1.5 months of cover" />
    </div>

    <section class="card flex flex-col gap-4" aria-labelledby="inv-h">
        <h2 id="inv-h" class="card-title">Stock by product</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Product</th><th scope="col" class="r">Opening</th><th scope="col" class="r">Purchases</th><th scope="col" class="r">Sold + bonus</th>
                    <th scope="col" class="r">Returns</th><th scope="col" class="r">Closing</th><th scope="col" class="r">Value at cost</th><th scope="col" class="r">Value at Ex-FP</th>
                    <th scope="col" class="r">Sales tax</th><th scope="col" class="r">WHT</th><th scope="col" class="r">Lasts</th><th scope="col">Stock</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-bold">{{ $r['name'] }}</td>
                        <td class="r">{{ fmt_num($r['opening']) }}</td>
                        <td class="r">{{ fmt_num($r['purchases']) }}</td>
                        <td class="r">{{ fmt_num($r['sold']) }}</td>
                        <td class="r">{{ fmt_num($r['returned']) }}</td>
                        <td class="r font-extrabold">{{ fmt_num($r['closing']) }}</td>
                        <td class="r">{{ fmt_num($r['value_cost']) }}</td>
                        <td class="r">{{ fmt_num($r['value_exfp']) }}</td>
                        <td class="r">{{ $r['sales_tax_rate'] !== null ? fmt_pct($r['sales_tax_rate']) : '–' }}</td>
                        <td class="r">{{ $r['withholding_tax_rate'] !== null ? fmt_pct($r['withholding_tax_rate']) : '–' }}</td>
                        <td class="r">{{ $r['cover'] !== null ? fmt_num($r['cover'], 1).' mo' : '–' }}</td>
                        <td><x-chip :tone="$levels[$r['level']][1]">{{ $levels[$r['level']][0] }}</x-chip></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td>Total</td><td class="r">{{ fmt_num($sum('opening')) }}</td><td class="r">{{ fmt_num($sum('purchases')) }}</td><td class="r">{{ fmt_num($sum('sold')) }}</td>
                    <td class="r">{{ fmt_num($sum('returned')) }}</td><td class="r">{{ fmt_num($sum('closing')) }}</td><td class="r">{{ fmt_num($sum('value_cost')) }}</td>
                    <td class="r">{{ fmt_num($sum('value_exfp')) }}</td><td></td><td></td><td></td><td></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-[12px] text-muted">Each product carries its own sales tax and withholding tax rate, set on Products &amp; taxes with an effective date. “Lasts” is closing stock divided by average units out per month over the last three months.</p>
    </section>
</x-layouts.app>
