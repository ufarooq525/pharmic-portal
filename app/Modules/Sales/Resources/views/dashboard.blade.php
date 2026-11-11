@php
    $q = ['period' => $period->format('Y-m')];
    $confirmed = collect($checklist)->where('tone', 'ok')->count();
    $total = count($checklist);
    $t = $totals;
    $margin = $t['value_exfp'] > 0 ? $t['gross_profit'] / $t['value_exfp'] * 100 : 0;
    $trendMax = max(1, ...array_column($trend, 'value'));
    $taxBase = $t['value_tp'];
    $levels = ['out' => ['Out of stock', 'bad'], 'low' => ['Low', 'warn'], 'ok' => ['OK', 'ok']];
@endphp
<x-layouts.app title="Dashboard" description="Month-end overview for {{ $period->format('F Y') }}: files received, sales, profit, taxes, incentive and stock.">
    <x-page-header :title="$period->format('F Y').' at a glance'" sub="Only confirmed imports count. {{ $confirmed }} of {{ $total }} distributor files are confirmed.">
        <a href="{{ route('imports.index', $q) }}" class="btn btn-primary">Upload a file</a>
        <a href="{{ route('reports.export', ['type' => 'sales'] + $q) }}" class="btn">Export sales (CSV)</a>
    </x-page-header>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3.5">
        <x-kpi label="Units sold" :value="fmt_num($t['units'])" :delta="$deltas['units']" :note="'+ '.fmt_num($t['bonus_units']).' bonus units'" />
        <x-kpi label="Sales at Ex-FP" :value="fmt_rs($t['value_exfp'])" :delta="$deltas['value_exfp']" :note="$hasPrevious ? 'Achieved value' : 'First month on record'" />
        <x-kpi label="Gross profit" :value="fmt_rs($t['gross_profit'])" :delta="$deltas['gross_profit']" :note="fmt_pct($margin).' of sales'" />
        <x-kpi label="Taxes to deposit" :value="fmt_rs($t['sales_tax'] + $t['withholding_tax'])" :note="'ST '.fmt_num($t['sales_tax']).' + WHT '.fmt_num($t['withholding_tax'])" />
        <x-kpi label="MR incentive" :value="fmt_rs($t['incentive'])" :delta="$deltas['incentive']" note="Percentage scheme" />
        <x-kpi label="Bonus given" :value="fmt_rs($t['bonus_cost'])" :note="'at cost · '.fmt_num($t['customers']).' customers bought'" />
    </div>

    <div class="blocks">
        <section class="card block-full" aria-labelledby="todo-h">
            <h2 id="todo-h" class="card-title">Needs your attention</h2>
            @if ($actions)
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th scope="col">What</th><th scope="col">Detail</th><th scope="col">Next step</th></tr></thead>
                        <tbody>
                        @foreach ($actions as $a)
                            <tr>
                                <td class="font-bold whitespace-nowrap">{{ $a['what'] }}</td>
                                <td>{{ $a['detail'] }}</td>
                                <td><a href="{{ $a['url'] }}" class="chip chip-{{ $a['tone'] }} text-[12px]">{{ $a['cta'] }} →</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="m-0 text-[13px] text-muted">Month end can close when this list is empty.</p>
            @else
                <x-empty>Nothing waiting. Every file is in and confirmed, every customer has a rep, and stock is healthy.</x-empty>
            @endif
        </section>

        <section class="card block-half" aria-labelledby="trend-h">
            <h2 id="trend-h" class="card-title">Sales over six months</h2>
            <ol class="m-0 grid h-52 list-none grid-cols-6 items-end gap-3 p-0" aria-label="Monthly sales at Ex-FP">
                @foreach ($trend as $m)
                    @php($current = $m['month'] === $period->format('Y-m'))
                    <li class="flex h-full flex-col items-center justify-end gap-1.5">
                        <span class="font-mono text-[11.5px] {{ $current ? 'font-medium text-accent-text' : 'text-muted' }}">{{ $m['value'] > 0 ? fmt_num($m['value'] / 1000).'k' : '–' }}</span>
                        <a href="{{ period_url($m['month']) }}" aria-label="{{ $m['label'] }}: {{ fmt_rs($m['value']) }}"
                           class="block w-full max-w-14 rounded-t-[10px] {{ $current ? 'bg-accent' : 'bg-soft hover:bg-seg' }}"
                           style="height: {{ max(3, round($m['value'] / $trendMax * 100)) }}%"></a>
                        <span class="text-[12px] font-bold {{ $current ? 'text-ink' : 'text-muted' }}">{{ $m['label'] }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="m-0 text-[13px] text-muted">Value at Ex-FP price. Click a month to open it; earlier months fill in as their files are imported.</p>
        </section>

        <section class="card block-half" aria-labelledby="dist-h">
            <h2 id="dist-h" class="card-title">Sales by distributor</h2>
            <x-bar-list :items="$byDistributor" />
            <p class="m-0 text-[13px] text-muted">Value at Ex-FP price, Rs</p>
        </section>

        <section class="card block-half" aria-labelledby="prod-h">
            <h2 id="prod-h" class="card-title">Units by product</h2>
            <x-bar-list :items="$byProduct" />
            <p class="m-0 text-[13px] text-muted">Sold units, bonus not included</p>
        </section>

        <section class="card block-half" aria-labelledby="cust-h">
            <h2 id="cust-h" class="card-title">Top customers</h2>
            @if ($topCustomers)
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th scope="col">Customer</th><th scope="col">Distributor</th><th scope="col" class="r">Units</th><th scope="col" class="r">Value Rs</th></tr></thead>
                        <tbody>
                        @foreach ($topCustomers as $c)
                            <tr>
                                <td><span class="block font-semibold">{{ $c['name'] }}</span><span class="text-[12px] text-muted">{{ $c['area'] ?: 'No area' }}</span></td>
                                <td>{{ str($c['distributor'])->before(' ') }}</td>
                                <td class="r">{{ fmt_num($c['units']) }}</td>
                                <td class="r">{{ fmt_num($c['value']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty>No confirmed sales for this month yet.</x-empty>
            @endif
        </section>

        <section class="card block-half" aria-labelledby="rep-h">
            <h2 id="rep-h" class="card-title">Medical rep performance</h2>
            @if ($byRep['rows'])
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th scope="col">Medical rep</th><th scope="col">Territory</th><th scope="col" class="r">Sales Rs</th><th scope="col" class="r">Incentive Rs</th></tr></thead>
                        <tbody>
                        @foreach ($byRep['rows'] as $r)
                            <tr>
                                <td class="font-semibold">{{ $r['name'] }}</td>
                                <td>{{ $r['territory'] }}</td>
                                <td class="r">{{ fmt_num($r['value']) }}</td>
                                <td class="r">{{ fmt_num($r['incentive']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty>No customers with sales are linked to a medical rep yet.</x-empty>
            @endif
            @if ($byRep['waiting'] > 0)
                <p class="m-0 text-[13px] text-muted">{{ fmt_rs($byRep['waiting']) }} more is waiting on customers with no rep. <a href="{{ route('customers.index', $q + ['status' => 'needs_rep']) }}">Assign reps</a></p>
            @endif
        </section>

        <section class="card block-half" aria-labelledby="tax-h">
            <h2 id="tax-h" class="card-title">Taxes for {{ $period->format('F') }}</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Tax</th><th scope="col" class="r">On value Rs</th><th scope="col" class="r">Amount Rs</th></tr></thead>
                    <tbody>
                    <tr><td class="font-semibold">Sales tax</td><td class="r">{{ fmt_num($taxBase) }}</td><td class="r">{{ fmt_num($t['sales_tax']) }}</td></tr>
                    <tr><td class="font-semibold">Withholding tax</td><td class="r">{{ fmt_num($taxBase) }}</td><td class="r">{{ fmt_num($t['withholding_tax']) }}</td></tr>
                    </tbody>
                    <tfoot><tr><td>Total to deposit</td><td></td><td class="r">{{ fmt_num($t['sales_tax'] + $t['withholding_tax']) }}</td></tr></tfoot>
                </table>
            </div>
            <p class="m-0 text-[13px] text-muted">Worked out per product from each product’s own rates. <a href="{{ route('reports.show', ['type' => 'taxes'] + $q) }}">Tax report</a></p>
        </section>

        <section class="card block-half" aria-labelledby="stock-h">
            <h2 id="stock-h" class="card-title">Stock watch</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Product</th><th scope="col" class="r">Closing units</th><th scope="col" class="r">Lasts</th><th scope="col">Stock</th></tr></thead>
                    <tbody>
                    @foreach (array_slice($stock, 0, 6) as $s)
                        <tr>
                            <td class="font-semibold">{{ $s['name'] }}</td>
                            <td class="r">{{ fmt_num($s['closing']) }}</td>
                            <td class="r">{{ $s['cover'] !== null ? fmt_num($s['cover'], 1).' mo' : '–' }}</td>
                            <td><x-chip :tone="$levels[$s['level']][1]">{{ $levels[$s['level']][0] }}</x-chip></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="m-0 text-[13px] text-muted">The six products with the least stock left. “Lasts” is closing stock ÷ average units out per month (sold + bonus, last three months).</p>
        </section>

        <section class="card block-half" aria-labelledby="close-h">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="close-h" class="card-title">Month-end checklist</h2>
                <span class="num font-medium text-accent-text">{{ $confirmed }}/{{ $total }}</span>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Distributor</th><th scope="col">City</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @foreach ($checklist as $row)
                        <tr>
                            <td class="font-semibold">{{ $row['name'] }}</td>
                            <td>{{ $row['city'] }}</td>
                            <td>
                                <a href="{{ $row['import_id'] ? route('imports.show', $row['import_id']) : route('imports.index', $q + ['distributor' => $row['distributor_id']]) }}" class="chip chip-{{ $row['tone'] }}">{{ $row['label'] }}</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
