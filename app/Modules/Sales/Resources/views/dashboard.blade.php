@php
    $q = ['period' => $period->format('Y-m')];
    $confirmed = collect($checklist)->where('tone', 'ok')->count();
    $total = count($checklist);
    $t = $totals;
    $margin = $t['value_exfp'] > 0 ? $t['gross_profit'] / $t['value_exfp'] * 100 : 0;
    $trendMax = max(1, ...array_column($trend, 'value'));
@endphp
<x-layouts.app title="Dashboard" description="Month-end overview for {{ $period->format('F Y') }}: files received, sales, profit, taxes, incentive and stock cover.">
    <x-page-header :title="$period->format('F Y')" sub="{{ $confirmed }} of {{ $total }} distributor files confirmed. Figures below count confirmed files only.">
        <a href="{{ route('imports.index', $q) }}" class="btn btn-primary">Upload a file</a>
        <a href="{{ route('reports.export', ['type' => 'sales'] + $q) }}" class="btn">Export sales (CSV)</a>
    </x-page-header>

    {{-- 1. Close the month: progress and the to-do list --}}
    <div class="grid gap-5 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <section class="card flex flex-col gap-4" aria-labelledby="close-h">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="close-h" class="card-title">Month-end close</h2>
                <span class="num font-extrabold text-accent">{{ $confirmed }}/{{ $total }}</span>
            </div>
            <div class="bar-track h-2.5" role="progressbar" aria-label="Files confirmed" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $confirmed }}">
                <span class="bar-fill" style="width: {{ $total ? round($confirmed / $total * 100) : 0 }}%"></span>
            </div>
            <ul class="flex flex-col divide-y divide-line">
                @foreach ($checklist as $row)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="min-w-0"><span class="block truncate font-bold">{{ $row['name'] }}</span><span class="text-[12px] text-muted">{{ $row['city'] }}</span></span>
                        @if ($row['import_id'])
                            <a href="{{ route('imports.show', $row['import_id']) }}" class="chip chip-{{ $row['tone'] }} no-underline">{{ $row['label'] }}</a>
                        @else
                            <a href="{{ route('imports.index', $q + ['distributor' => $row['distributor_id']]) }}" class="chip chip-{{ $row['tone'] }} no-underline">{{ $row['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card flex flex-col gap-4" aria-labelledby="todo-h">
            <h2 id="todo-h" class="card-title">Needs your attention</h2>
            @forelse ($actions as $a)
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-{{ $a['tone'] }}-soft px-4 py-3">
                    <span class="flex items-start gap-2.5 text-ink">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-{{ $a['tone'] }}" aria-hidden="true"></span>
                        <span>{{ $a['text'] }}</span>
                    </span>
                    <a href="{{ $a['url'] }}" class="btn btn-sm shrink-0">{{ $a['cta'] }}</a>
                </div>
            @empty
                <x-empty>Nothing waiting. Every file is in and confirmed, every customer has a rep, and stock is healthy.</x-empty>
            @endforelse
        </section>
    </div>

    {{-- 2. The numbers that matter, with the change against last month --}}
    <div class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3.5">
        <x-kpi label="Sales at Ex-FP" :value="fmt_rs($t['value_exfp'])" :delta="$deltas['value_exfp']" :note="$hasPrevious ? null : 'first month on record'" />
        <x-kpi label="Gross profit" :value="fmt_rs($t['gross_profit'])" :delta="$deltas['gross_profit']" :note="fmt_pct($margin).' margin'" />
        <x-kpi label="Units sold" :value="fmt_num($t['units'])" :delta="$deltas['units']" :note="'+ '.fmt_num($t['bonus_units']).' bonus'" />
        <x-kpi label="Tax to account for" :value="fmt_rs($t['sales_tax'] + $t['withholding_tax'])" :note="'ST '.fmt_rs($t['sales_tax']).' · WHT '.fmt_rs($t['withholding_tax'])" />
        <x-kpi label="MR incentive due" :value="fmt_rs($t['incentive'])" :delta="$deltas['incentive']" />
        <x-kpi label="Bonus given (at cost)" :value="fmt_rs($t['bonus_cost'])" :note="fmt_num($t['customers']).' customers bought'" />
    </div>

    {{-- 3. Trend and where sales came from --}}
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card flex flex-col gap-4" aria-labelledby="trend-h">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="trend-h" class="card-title">Sales over six months</h2>
                <span class="text-[12px] text-muted">Ex-FP value, Rs</span>
            </div>
            <ol class="grid h-56 grid-cols-6 items-end gap-3" aria-label="Monthly sales">
                @foreach ($trend as $m)
                    @php($current = $m['month'] === $period->format('Y-m'))
                    <li class="flex h-full flex-col items-center justify-end gap-1.5">
                        <span class="num text-[11.5px] font-bold {{ $current ? 'text-accent' : 'text-muted' }}">{{ $m['value'] > 0 ? fmt_num($m['value'] / 1000).'k' : '–' }}</span>
                        <a href="{{ period_url($m['month']) }}" aria-label="{{ $m['label'] }}: {{ fmt_rs($m['value']) }}"
                           class="block w-full max-w-14 rounded-t-lg {{ $current ? 'bg-accent' : 'bg-accent-soft hover:bg-accent/40' }}"
                           style="height: {{ max(3, round($m['value'] / $trendMax * 100)) }}%"></a>
                        <span class="text-[12px] font-bold {{ $current ? 'text-ink' : 'text-muted' }}">{{ $m['label'] }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="text-[12px] text-muted">Click a month to open it. Earlier months fill in as their files are imported.</p>
        </section>

        <section class="card flex flex-col gap-4" aria-labelledby="dist-h">
            <h2 id="dist-h" class="card-title">Sales by distributor</h2>
            <x-bar-list :items="$byDistributor" />
        </section>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card flex flex-col gap-4" aria-labelledby="rep-h">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="rep-h" class="card-title">Sales by medical rep</h2>
                <a href="{{ route('reports.show', ['type' => 'incentive'] + $q) }}" class="text-[13px] font-bold">Incentive report</a>
            </div>
            <x-bar-list :items="$byRep" empty="No customers with sales are linked to a medical rep yet." />
        </section>

        <section class="card flex flex-col gap-4" aria-labelledby="prod-h">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="prod-h" class="card-title">Products</h2>
                <a href="{{ route('reports.show', ['type' => 'sales'] + $q) }}" class="text-[13px] font-bold">Sales report</a>
            </div>
            <x-bar-list :items="$byProduct" />
        </section>
    </div>

    {{-- 4. Stock that will run out, and the customers that matter most --}}
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card flex flex-col gap-4" aria-labelledby="stock-h">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="stock-h" class="card-title">Stock cover</h2>
                <a href="{{ route('inventory.index', $q) }}" class="text-[13px] font-bold">Inventory</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Product</th><th scope="col" class="r">Closing</th><th scope="col" class="r">Per month</th><th scope="col" class="r">Lasts</th><th scope="col"><span class="sr-only">Level</span></th></tr></thead>
                    <tbody>
                    @foreach (array_slice($stock, 0, 8) as $s)
                        <tr>
                            <td class="font-semibold">{{ $s['name'] }}</td>
                            <td class="r">{{ fmt_num($s['closing']) }}</td>
                            <td class="r">{{ $s['monthly'] > 0 ? fmt_num($s['monthly']) : '–' }}</td>
                            <td class="r">{{ $s['cover'] !== null ? fmt_num($s['cover'], 1).' mo' : 'no sales' }}</td>
                            <td><x-chip :tone="['out' => 'bad', 'low' => 'warn', 'ok' => 'ok'][$s['level']]">{{ ['out' => 'Out', 'low' => 'Reorder', 'ok' => 'OK'][$s['level']] }}</x-chip></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[12px] text-muted">“Lasts” is closing stock divided by average units out (sold + bonus) per month over the last three months.</p>
        </section>

        <section class="card flex flex-col gap-4" aria-labelledby="cust-h">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="cust-h" class="card-title">Top customers</h2>
                <a href="{{ route('reports.show', ['type' => 'customers'] + $q) }}" class="text-[13px] font-bold">All customers</a>
            </div>
            @if ($topCustomers)
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th scope="col">Customer</th><th scope="col">Distributor</th><th scope="col" class="r">Units</th><th scope="col" class="r">Value TP</th></tr></thead>
                        <tbody>
                        @foreach ($topCustomers as $c)
                            <tr>
                                <td><span class="block font-semibold">{{ $c['name'] }}</span><span class="text-[12px] text-muted">{{ $c['area'] ?: 'No area' }}</span></td>
                                <td>{{ $c['distributor'] }}</td>
                                <td class="r">{{ fmt_num($c['units']) }}</td>
                                <td class="r">{{ fmt_rs($c['value']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty>No confirmed sales for this month yet.</x-empty>
            @endif
        </section>
    </div>
</x-layouts.app>
