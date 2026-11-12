@php
    $q = ['period' => $period->format('Y-m')];
    $t = $totals;
    $confirmed = collect($checklist)->where('tone', 'ok')->count();
    $total = max(1, count($checklist));
    $margin = $t['value_exfp'] > 0 ? $t['gross_profit'] / $t['value_exfp'] * 100 : 0;
    $levels = ['out' => ['Out of stock', 'bad'], 'low' => ['Reorder', 'warn'], 'ok' => ['OK', 'ok']];
    $palette = ['var(--sky-c1)', 'var(--sky-c2)', 'var(--sky-c3)', 'var(--sky-c4)', 'var(--sky-c5)', 'var(--sky-c6)', 'var(--sky-c7)'];
    $toneIcon = ['bad' => '!', 'warn' => '•', 'mute' => '↑', 'ok' => '✓', 'info' => 'i'];

    // Six-month area chart (viewBox 600 × 200, 24px padding).
    $tmax = max(1, ...array_column($trend, 'value'));
    $pts = collect($trend)->values()->map(fn ($m, $i) => [
        'x' => round(24 + $i * (552 / max(1, count($trend) - 1)), 1),
        'y' => round(176 - ($m['value'] / $tmax) * 140, 1),
    ] + $m);
    $line = $pts->map(fn ($p) => $p['x'].','.$p['y'])->join(' ');
    $area = 'M'.$pts->first()['x'].',176 L'.$pts->map(fn ($p) => $p['x'].','.$p['y'])->join(' L').' L'.$pts->last()['x'].',176 Z';

    $open = count($checklist) - $confirmed;
    $distMax = max(1, ...array_map(fn ($d) => (float) $d['value'], $byDistributor ?: [['value' => 1]]));
    $filtered = array_filter($filters);
    $canUpload = auth()->user()->hasRole('accounts', 'data_entry');
    $initials = fn (string $name) => collect(preg_split('/\s+/', trim($name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $productMax = max(1, ...array_map(fn ($i) => (float) $i['value'], $byProduct ?: [['value' => 1]]));
@endphp
<x-layouts.app title="Dashboard" description="{{ $period->format('F Y') }} at a glance: units, sales at Ex-FP, gross profit, sales tax, MR incentive and the month-end checklist.">
    <x-page-header :title="$period->format('F Y').' at a glance'"
                   :sub="'Only confirmed imports count. '.($open > 0 ? $open.' of '.count($checklist).' '.str('distributor')->plural(count($checklist)).' still need attention before month end can close.' : 'Every distributor’s file is confirmed, so the month can close.')">
        @if ($canUpload)
            <a href="{{ route('imports.index', $q) }}" class="btn btn-primary">Upload a file</a>
        @endif
        <a href="{{ route('reports.export', ['type' => 'sales'] + $q + $filtered) }}" class="btn">Export CSV</a>
    </x-page-header>

    {{-- Filters: the same ones the reports use, so both screens agree --}}
    <form method="get" class="flex flex-wrap items-end gap-2.5" aria-label="Dashboard filters">
        <div class="flex flex-col gap-1">
            <label for="d-period" class="label">Month</label>
            <input id="d-period" name="period" type="month" value="{{ $period->format('Y-m') }}" class="field min-h-10 w-auto py-1.5" data-autosubmit>
        </div>
        @foreach ($options as $name => [$all, $list])
            <div class="flex flex-col gap-1">
                <label for="d-{{ $name }}" class="label">{{ str($all)->after('All ')->ucfirst()->singular() }}</label>
                <select id="d-{{ $name }}" name="{{ $name }}" class="field min-h-10 w-auto min-w-[170px] py-1.5" data-autosubmit>
                    <option value="">{{ $all }}</option>
                    @foreach ($list as $o)
                        <option value="{{ $o->id }}" @selected($filters[$name] === $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        @if ($filtered)
            <a href="{{ route('dashboard', $q) }}" class="btn btn-sm mb-1">Clear filters</a>
        @endif
        <noscript><button class="btn btn-sm">Apply</button></noscript>
    </form>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 gap-3.5 md:grid-cols-3 xl:grid-cols-5">
        <x-kpi icon="box" label="Units sold" :value="fmt_num($t['units'])" :delta="$deltas['units']" :note="'+ '.fmt_num($t['bonus_units']).' bonus units'" />
        <x-kpi icon="trend" label="Sales at Ex-FP" :value="fmt_rs($t['value_exfp'])" :delta="$deltas['value_exfp']" note="Achieved value" />
        <x-kpi icon="profit" label="Gross profit" :value="fmt_rs($t['gross_profit'])" :delta="$deltas['gross_profit']" :note="fmt_pct($margin).' of sales'" />
        <x-kpi icon="receipt" label="Sales tax" :value="fmt_rs($t['sales_tax'])" :note="'Per product rate · WHT '.fmt_num($t['withholding_tax'])" />
        <x-kpi icon="people" label="MR incentive" :value="fmt_rs($t['incentive'])" :delta="$deltas['incentive']" note="Percentage scheme" />
    </div>

    <div class="blocks">
        {{-- Sales by distributor --}}
        <section class="card flex-[1_1_420px]" aria-labelledby="dist-h">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="dist-h" class="card-title">Sales by distributor</h2>
                <span class="text-[13px] text-muted">Value at Ex-FP</span>
            </div>
            @if ($byDistributor)
                <ul class="m-0 flex list-none flex-col gap-3.5 p-0">
                    @foreach ($byDistributor as $i => $d)
                        <li class="flex flex-col gap-1.5">
                            <span class="flex items-baseline justify-between gap-3">
                                <b class="truncate">{{ $d['label'] }}</b>
                                <span class="num text-[13px] font-medium">{{ $d['display'] }}</span>
                            </span>
                            <span class="bar-track h-3" aria-hidden="true"><span class="block h-full rounded-[7px]" style="width: {{ max(2, round($d['value'] / $distMax * 100)) }}%; background: {{ $palette[$i % count($palette)] }}"></span></span>
                            <span class="text-[12px] text-muted">{{ $d['sub'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty>No confirmed sales for this month{{ $filtered ? ' with these filters' : '' }} yet.</x-empty>
            @endif
        </section>

        {{-- Month-end checklist --}}
        <section class="card flex-[1_1_420px]" aria-labelledby="close-h">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="close-h" class="card-title">Month-end checklist <span class="num ml-1 text-[15px] font-medium text-muted">{{ $confirmed }}/{{ count($checklist) }}</span></h2>
                <a href="{{ route('imports.index', $q) }}" class="btn btn-sm">Open imports</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Distributor</th><th scope="col">City</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @foreach ($checklist as $row)
                        <tr>
                            <td class="font-bold">
                                <a href="{{ $row['import_id'] ? route('imports.show', $row['import_id']) : route('imports.index', $q + ['distributor' => $row['distributor_id']]) }}" class="text-ink no-underline hover:underline">{{ $row['name'] }}</a>
                            </td>
                            <td class="text-muted">{{ $row['city'] }}</td>
                            <td><x-chip :tone="$row['tone']">{{ $row['label'] }}</x-chip></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Units by product --}}
        <section class="card block-full" aria-labelledby="prod-h">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="prod-h" class="card-title">Units by product</h2>
                <span class="text-[13px] text-muted">Sold units, excluding bonus</span>
            </div>
            @if ($byProduct)
                <ul class="m-0 grid list-none gap-x-8 gap-y-3.5 p-0 lg:grid-cols-2">
                    @foreach ($byProduct as $i => $item)
                        <li class="flex flex-col gap-1.5">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2"><span class="dot" style="background: {{ $palette[$i % count($palette)] }}"></span><b class="truncate">{{ $item['label'] }}</b></span>
                                <span class="num text-[13px] font-medium">{{ $item['display'] }}</span>
                            </span>
                            <span class="bar-track h-2.5" aria-hidden="true"><span class="block h-full rounded-[7px]" style="width: {{ max(2, round((float) $item['value'] / $productMax * 100)) }}%; background: {{ $palette[$i % count($palette)] }}"></span></span>
                            <span class="text-[12px] text-muted">{{ $item['sub'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty>No confirmed sales for this month{{ $filtered ? ' with these filters' : '' }} yet.</x-empty>
            @endif
        </section>

        {{-- Needs your attention --}}
        <section class="card block-full" aria-labelledby="todo-h">
            <div class="flex items-center gap-2.5">
                <h2 id="todo-h" class="card-title">Needs your attention</h2>
                @if ($actions)<span class="chip chip-warn">{{ count($actions) }}</span>@endif
            </div>
            @if ($actions)
                <ul class="m-0 grid list-none gap-2.5 p-0 lg:grid-cols-2">
                    @foreach ($actions as $a)
                        <li class="flex items-center gap-3 rounded-2xl bg-sunk px-3.5 py-3">
                            <span class="flex size-9 flex-none items-center justify-center rounded-full bg-{{ $a['tone'] }}-soft font-extrabold text-{{ $a['tone'] }}" aria-hidden="true">{{ $toneIcon[$a['tone']] ?? '•' }}</span>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <b class="truncate">{{ $a['what'] }}</b>
                                <span class="text-[13px] text-muted">{{ $a['detail'] }}</span>
                            </span>
                            <a href="{{ $a['url'] }}" class="btn btn-sm flex-none">{{ $a['cta'] }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex items-center gap-3 rounded-2xl bg-ok-soft px-4 py-3.5 font-semibold text-ok">
                    <span class="flex size-9 items-center justify-center rounded-full bg-ok text-surface font-extrabold" aria-hidden="true">✓</span>
                    All clear. Every file is confirmed, every customer has a rep and stock is healthy, so the month can close.
                </div>
            @endif
        </section>

        {{-- Trend --}}
        <section class="card block-half" aria-labelledby="trend-h">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="trend-h" class="card-title">Sales over six months</h2>
                <span class="text-[13px] text-muted">Value at Ex-FP</span>
            </div>
            <figure class="m-0">
                <svg viewBox="0 0 600 206" class="h-auto w-full" role="img" aria-label="Monthly sales at Ex-FP: {{ collect($trend)->map(fn ($m) => $m['label'].' '.fmt_rs($m['value']))->join(', ') }}">
                    <defs>
                        <linearGradient id="trend-fill" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="var(--sky-accent)" stop-opacity="0.28"/>
                            <stop offset="100%" stop-color="var(--sky-accent)" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    @foreach ([36, 82, 129, 176] as $gy)
                        <line x1="24" x2="576" y1="{{ $gy }}" y2="{{ $gy }}" stroke="var(--sky-hair)" stroke-width="1"/>
                    @endforeach
                    <path d="{{ $area }}" fill="url(#trend-fill)"/>
                    <polyline points="{{ $line }}" fill="none" stroke="var(--sky-accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    @foreach ($pts as $p)
                        @php($isNow = $p['month'] === $period->format('Y-m'))
                        <a href="{{ period_url($p['month']) }}">
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="{{ $isNow ? 7 : 5 }}" fill="{{ $isNow ? 'var(--sky-accent)' : 'var(--sky-surface)' }}" stroke="var(--sky-accent)" stroke-width="3"/>
                            <text x="{{ $p['x'] }}" y="{{ max(14, $p['y'] - 14) }}" text-anchor="middle" font-size="12" font-family="Roboto Mono, monospace" fill="{{ $isNow ? 'var(--sky-accent-text)' : 'var(--sky-muted)' }}" font-weight="{{ $isNow ? 600 : 400 }}">{{ $p['value'] > 0 ? fmt_num($p['value'] / 1000).'k' : '–' }}</text>
                        </a>
                        <text x="{{ $p['x'] }}" y="200" text-anchor="middle" font-size="12.5" font-weight="700" fill="{{ $isNow ? 'var(--sky-ink)' : 'var(--sky-muted)' }}">{{ $p['label'] }}</text>
                    @endforeach
                </svg>
            </figure>
            <p class="m-0 text-[12px] text-muted">Click a point to open that month.</p>
        </section>

        {{-- Top customers --}}
        <section class="card block-half" aria-labelledby="cust-h">
            <h2 id="cust-h" class="card-title">Top customers</h2>
            @if ($topCustomers)
                <ol class="m-0 flex list-none flex-col p-0">
                    @foreach ($topCustomers as $i => $c)
                        <li class="flex items-center gap-3 border-b border-hair py-2.5 last:border-0">
                            <span class="rank {{ $i === 0 ? 'bg-accent text-white' : '' }}">{{ $i + 1 }}</span>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <b class="truncate">{{ $c['name'] }}</b>
                                <span class="truncate text-[12px] text-muted">{{ $c['area'] ?: 'No area' }} · {{ str($c['distributor'])->before(' ') }} · {{ fmt_num($c['units']) }} units</span>
                            </span>
                            <span class="num text-right font-medium">{{ fmt_num($c['value']) }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="m-0 text-[12px] text-muted">Value at trade price, Rs</p>
            @else
                <x-empty>No confirmed sales for this month yet.</x-empty>
            @endif
        </section>

        {{-- Reps --}}
        <section class="card block-half" aria-labelledby="rep-h">
            <h2 id="rep-h" class="card-title">Medical rep leaderboard</h2>
            @if ($byRep['rows'])
                @php($repMax = max(1, ...array_column($byRep['rows'], 'value')))
                <ul class="m-0 flex list-none flex-col gap-3 p-0">
                    @foreach ($byRep['rows'] as $i => $r)
                        <li class="flex items-center gap-3">
                            <span class="flex size-10 flex-none items-center justify-center rounded-full text-[13px] font-extrabold text-white" style="background: {{ $palette[$i % count($palette)] }}" aria-hidden="true">{{ $initials($r['name']) }}</span>
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="flex items-baseline justify-between gap-2"><b class="truncate">{{ $r['name'] }}</b><span class="num text-[13px]">{{ fmt_num($r['value']) }}</span></span>
                                <span class="bar-track h-2" aria-hidden="true"><span class="bar-fill" style="width: {{ max(2, round($r['value'] / $repMax * 100)) }}%"></span></span>
                                <span class="text-[12px] text-muted">{{ $r['territory'] }} · incentive {{ fmt_rs($r['incentive']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty>No customers with sales are linked to a medical rep yet.</x-empty>
            @endif
            @if ($byRep['waiting'] > 0)
                <p class="m-0 rounded-[12px] bg-warn-soft px-3 py-2 text-[13px] text-warn">{{ fmt_rs($byRep['waiting']) }} incentive is waiting on customers with no rep. <a href="{{ route('customers.index', $q + ['status' => 'needs_rep']) }}" class="font-bold text-warn">Assign reps</a></p>
            @endif
        </section>

        {{-- Money --}}
        <section class="card block-half" aria-labelledby="money-h">
            <h2 id="money-h" class="card-title">Money this month</h2>
            <dl class="m-0 grid grid-cols-2 gap-2.5">
                @foreach (array_filter([
                    ['Invoiced to distributors', fmt_rs($money['invoiced']), 'incl. sales tax and WHT', route('ledger.index', $q)],
                    ['Received', fmt_rs($money['received']), 'payments recorded', route('ledger.index', $q)],
                    ['Invoices issued', fmt_num($money['invoices']), fmt_rs($money['invoicesTotal']), auth()->user()->hasRole('accounts') ? route('invoices.index', $q) : null],
                    $money['payroll'] !== null ? ['Net payroll', fmt_rs($money['payroll']), 'base + incentive', route('payroll.index', $q)] : null,
                ]) as [$label, $value, $sub, $url])
                    <div class="flex flex-col gap-1 rounded-2xl bg-sunk px-3.5 py-3">
                        <dt class="text-[12px] font-bold text-muted">{{ $label }}</dt>
                        <dd class="m-0 num text-[19px] font-medium">@if ($url)<a href="{{ $url }}" class="text-ink no-underline hover:underline">{{ $value }}</a>@else{{ $value }}@endif</dd>
                        <dd class="m-0 text-[12px] text-muted">{{ $sub }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="flex items-center justify-between gap-3 rounded-2xl bg-soft px-4 py-3 text-softfg">
                <span class="font-bold">Taxes to deposit</span>
                <span class="num text-[19px] font-medium">{{ fmt_rs($t['sales_tax'] + $t['withholding_tax']) }}</span>
            </div>
        </section>

        {{-- Stock --}}
        <section class="card block-half" aria-labelledby="stock-h">
            <div class="flex items-baseline justify-between gap-2">
                <h2 id="stock-h" class="card-title">Stock watch</h2>
                <a href="{{ route('inventory.index', $q) }}" class="text-[13px] font-bold">Inventory →</a>
            </div>
            <ul class="m-0 flex list-none flex-col gap-3 p-0">
                @foreach (array_slice($stock, 0, 6) as $s)
                    @php([$lvl, $tone] = $levels[$s['level']])
                    <li class="flex items-center gap-3">
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="flex items-baseline justify-between gap-2"><b class="truncate">{{ $s['name'] }}</b><span class="num text-[13px] text-muted">{{ fmt_num($s['closing']) }} units</span></span>
                            <span class="bar-track h-2" aria-hidden="true"><span class="block h-full rounded-[7px] bg-{{ $tone }}" style="width: {{ $s['cover'] === null ? 100 : max(3, min(100, round($s['cover'] / 6 * 100))) }}%"></span></span>
                        </span>
                        <span class="w-16 text-right text-[12px] text-muted">{{ $s['cover'] !== null ? fmt_num($s['cover'], 1).' mo' : '–' }}</span>
                        <x-chip :tone="$tone">{{ $lvl }}</x-chip>
                    </li>
                @endforeach
            </ul>
            <p class="m-0 text-[12px] text-muted">The six products with the least stock left. The bar shows months of cover, full at six months.</p>
        </section>

    </div>
</x-layouts.app>
