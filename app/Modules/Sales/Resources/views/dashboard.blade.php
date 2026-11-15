@php
    $q = ['period' => $period->format('Y-m')];
    $t = $totals;
    $confirmed = collect($checklist)->where('tone', 'ok')->count();
    $files = count($checklist);
    $donePct = $files ? round($confirmed / $files * 100) : 0;
    $margin = $t['value_exfp'] > 0 ? $t['gross_profit'] / $t['value_exfp'] * 100 : 0;
    $levels = ['out' => ['Out of stock', 'bad'], 'low' => ['Reorder', 'warn'], 'ok' => ['OK', 'ok']];
    $palette = ['var(--sky-c1)', 'var(--sky-c2)', 'var(--sky-c3)', 'var(--sky-c4)', 'var(--sky-c5)', 'var(--sky-c6)', 'var(--sky-c7)'];
    $attnIcon = ['bad' => 'alert', 'warn' => 'alert', 'mute' => 'upload', 'info' => 'file', 'ok' => 'check'];

    // Six-month area chart (viewBox 600 × 200, 24px padding).
    $tmax = max(1, ...array_column($trend, 'value'));
    $pts = collect($trend)->values()->map(fn ($m, $i) => [
        'x' => round(24 + $i * (552 / max(1, count($trend) - 1)), 1),
        'y' => round(176 - ($m['value'] / $tmax) * 140, 1),
    ] + $m);
    $line = $pts->map(fn ($p) => $p['x'].','.$p['y'])->join(' ');
    $area = 'M'.$pts->first()['x'].',176 L'.$pts->map(fn ($p) => $p['x'].','.$p['y'])->join(' L').' L'.$pts->last()['x'].',176 Z';
    $hasTrend = collect($trend)->sum('value') > 0;

    $open = $files - $confirmed;
    $distMax = max(1, ...array_map(fn ($d) => (float) $d['value'], $byDistributor ?: [['value' => 1]]));
    $filtered = array_filter($filters);
    $canUpload = auth()->user()->can('imports.upload');
    $initials = fn (string $name) => collect(preg_split('/\s+/', trim($name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $productMax = max(1, ...array_map(fn ($i) => (float) $i['value'], $byProduct ?: [['value' => 1]]));
    $noSales = 'No confirmed sales for '.$period->format('F').($filtered ? ' with these filters' : '').' yet';
    $noSalesHint = 'Figures appear here as soon as a distributor’s file is confirmed.';
    $upload = $canUpload && ! $filtered ? ['Upload a file', route('imports.index', $q)] : [null, null];
    $shownActions = 6;
@endphp
<x-layouts.app title="Dashboard" description="{{ $period->format('F Y') }} at a glance: units, sales at Ex-FP, gross profit, sales tax, MR incentive and the month-end checklist.">

    {{-- Month-end hero: where the month stands and what to do next --}}
    <section class="hero" aria-labelledby="dash-h">
        <span class="hero-glow" aria-hidden="true"></span>
        <div class="relative flex flex-wrap items-center justify-between gap-6">
            <div class="flex min-w-0 max-w-[62ch] flex-1 flex-col gap-3">
                <span class="hero-eyebrow self-start"><x-icon name="checklist" :size="14" /> Month end · {{ $period->format('F Y') }}</span>
                <h1 id="dash-h" class="m-0 text-[26px] font-extrabold tracking-[-0.025em] sm:text-[32px]">{{ $period->format('F Y') }} at a glance</h1>
                <p class="hero-sub m-0">
                    Only confirmed imports count.
                    {{ $open > 0 ? $open.' of '.$files.' '.str('distributor')->plural($files).' still need attention before the month can close.' : 'Every distributor’s file is confirmed, so the month can close.' }}
                </p>
                @if ($files)
                    <div class="flex flex-col gap-1.5">
                        <div class="close-track" aria-hidden="true">
                            @foreach ($checklist as $row)
                                <span class="close-seg" data-tone="{{ $row['tone'] }}" title="{{ $row['name'] }}: {{ $row['label'] }}"></span>
                            @endforeach
                        </div>
                        <span class="hero-sub text-[12px]">One bar per distributor · green is confirmed, amber needs a look</span>
                    </div>
                @endif
                <div class="mt-1 flex flex-wrap gap-2">
                    @if ($canUpload)
                        <a href="{{ route('imports.index', $q) }}" class="btn btn-light"><x-icon name="upload" :size="16" /> Upload a file</a>
                    @endif
                    <a href="{{ route('reports.export', ['type' => 'sales'] + $q + $filtered) }}" class="btn btn-ghost-dark"><x-icon name="download" :size="16" /> Export CSV</a>
                    <x-refresh-button class="btn-ghost-dark" />
                </div>
            </div>
            <div class="ring max-sm:hidden" style="--p: {{ $donePct }}" role="img" aria-label="{{ $confirmed }} of {{ $files }} files confirmed">
                <span class="flex flex-col items-center leading-none">
                    <span class="num text-[24px] font-semibold">{{ $confirmed }}<span class="text-[15px] text-[#9fb0d6]">/{{ $files }}</span></span>
                    <span class="mt-1 text-[11px] font-bold text-[#c3cbe0]">confirmed</span>
                </span>
            </div>
        </div>
    </section>

    {{-- Filters: the same ones the reports use, so both screens agree --}}
    <form method="get" class="toolbar" aria-label="Dashboard filters">
        <div class="flex flex-col gap-1">
            <label for="d-period" class="label">Month</label>
            <input id="d-period" name="period" type="month" value="{{ $period->format('Y-m') }}" class="field min-h-10 w-auto py-1.5" data-autosubmit>
        </div>
        @foreach ($options as $name => [$all, $list])
            <div class="flex min-w-0 flex-col gap-1">
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
            <a href="{{ route('dashboard', $q) }}" class="btn btn-sm mb-0.5">Clear filters</a>
        @endif
        <noscript><button class="btn btn-sm">Apply</button></noscript>
    </form>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 gap-3.5 md:grid-cols-3 xl:grid-cols-5">
        <x-kpi icon="box" tone="c1" label="Units sold" :value="fmt_num($t['units'])" :delta="$deltas['units']" :note="'+ '.fmt_num($t['bonus_units']).' bonus units'" />
        <x-kpi icon="trend" tone="c2" label="Sales at Ex-FP" :value="fmt_rs($t['value_exfp'])" :delta="$deltas['value_exfp']" note="Achieved value" />
        <x-kpi icon="profit" tone="c6" label="Gross profit" :value="fmt_rs($t['gross_profit'])" :delta="$deltas['gross_profit']" :note="fmt_pct($margin).' of sales'" />
        <x-kpi icon="receipt" tone="c3" label="Sales tax" :value="fmt_rs($t['sales_tax'])" :note="'Per product rate · WHT '.fmt_num($t['withholding_tax'])" />
        <x-kpi icon="people" tone="c4" label="MR incentive" :value="fmt_rs($t['incentive'])" :delta="$deltas['incentive']" note="Percentage scheme" />
    </div>

    <div class="dash-grid">
        {{-- Needs your attention --}}
        <section class="card span-7" aria-labelledby="todo-h">
            <x-card-head id="todo-h" title="Needs your attention" icon="bell" tone="c5">
                @if ($actions)<span class="chip chip-warn">{{ count($actions) }} to do</span>@endif
            </x-card-head>
            @if ($actions)
                <ul class="m-0 flex list-none flex-col gap-2 p-0">
                    @foreach (array_slice($actions, 0, $shownActions) as $a)
                        @include('sales::_attention', ['a' => $a, 'icon' => $attnIcon])
                    @endforeach
                </ul>
                @if (count($actions) > $shownActions)
                    <details class="attn-more">
                        <summary class="link-btn -ml-2 inline-flex items-center">Show {{ count($actions) - $shownActions }} more</summary>
                        <ul class="m-0 mt-2 flex list-none flex-col gap-2 p-0">
                            @foreach (array_slice($actions, $shownActions) as $a)
                                @include('sales::_attention', ['a' => $a, 'icon' => $attnIcon])
                            @endforeach
                        </ul>
                    </details>
                @endif
            @else
                <div class="flex flex-1 items-center gap-3 rounded-2xl bg-ok-soft px-4 py-3.5 font-semibold text-ok">
                    <span class="flex size-9 flex-none items-center justify-center rounded-full bg-ok text-surface"><x-icon name="check" /></span>
                    All clear. Every file is confirmed, every customer has a rep and stock is healthy, so the month can close.
                </div>
            @endif
        </section>

        {{-- Month-end checklist --}}
        <section class="card span-5" aria-labelledby="close-h">
            <x-card-head id="close-h" title="Month-end checklist" icon="checklist" tone="c6">
                <span class="num font-semibold">{{ $confirmed }}/{{ $files }}</span>
                <a href="{{ route('imports.index', $q) }}" class="btn btn-sm">Open imports</a>
            </x-card-head>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Distributor</th><th scope="col">City</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @foreach ($checklist as $row)
                        <tr>
                            <td class="font-bold">
                                <a href="{{ $row['import_id'] ? route('imports.show', $row['import_id']) : route('imports.index', $q + ['distributor' => $row['distributor_id']]) }}" class="text-ink no-underline hover:underline" data-row-target>{{ $row['name'] }}</a>
                            </td>
                            <td class="text-muted">{{ $row['city'] }}</td>
                            <td><x-chip :tone="$row['tone']">{{ $row['label'] }}</x-chip></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Sales by distributor --}}
        <section class="card span-6" aria-labelledby="dist-h">
            <x-card-head id="dist-h" title="Sales by distributor" icon="truck" tone="c1">Value at Ex-FP</x-card-head>
            @if ($byDistributor)
                <ul class="m-0 flex list-none flex-col gap-3.5 p-0">
                    @foreach ($byDistributor as $i => $d)
                        <li class="flex flex-col gap-1.5">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2"><span class="dot" style="background: {{ $palette[$i % count($palette)] }}"></span><b class="truncate">{{ $d['label'] }}</b></span>
                                <span class="num text-[13px] font-medium">{{ $d['display'] }}</span>
                            </span>
                            <span class="bar-track h-2.5" aria-hidden="true"><span class="block h-full rounded-[7px]" style="width: {{ max(2, round($d['value'] / $distMax * 100)) }}%; background: {{ $palette[$i % count($palette)] }}"></span></span>
                            <span class="text-[12px] text-muted">{{ $d['sub'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state icon="truck" :title="$noSales" :action="$upload[0]" :href="$upload[1]">{{ $noSalesHint }}</x-empty-state>
            @endif
        </section>

        {{-- Trend --}}
        <section class="card span-6" aria-labelledby="trend-h">
            <x-card-head id="trend-h" title="Sales over six months" icon="chart" tone="c2">Value at Ex-FP</x-card-head>
            <figure class="m-0 flex flex-1 flex-col justify-end">
                <svg viewBox="0 0 600 206" class="h-auto w-full" role="img" aria-label="Monthly sales at Ex-FP: {{ collect($trend)->map(fn ($m) => $m['label'].' '.fmt_rs($m['value']))->join(', ') }}">
                    <defs>
                        <linearGradient id="trend-fill" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="var(--sky-c2)" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="var(--sky-c2)" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    @foreach ([36, 82, 129, 176] as $gy)
                        <line x1="24" x2="576" y1="{{ $gy }}" y2="{{ $gy }}" stroke="var(--sky-hair)" stroke-width="1" @if ($gy !== 176) stroke-dasharray="4 5" @endif/>
                    @endforeach
                    @if ($hasTrend)
                        <path d="{{ $area }}" fill="url(#trend-fill)"/>
                        <polyline points="{{ $line }}" fill="none" stroke="var(--sky-c2)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    @else
                        <text x="300" y="110" text-anchor="middle" font-size="14" font-weight="600" fill="var(--sky-muted)">No confirmed sales in the last six months</text>
                    @endif
                    @foreach ($pts as $p)
                        @php($isNow = $p['month'] === $period->format('Y-m'))
                        <a href="{{ period_url($p['month']) }}" aria-label="Open {{ $p['label'] }}">
                            @if ($hasTrend)
                                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="{{ $isNow ? 7 : 5 }}" fill="{{ $isNow ? 'var(--sky-c2)' : 'var(--sky-surface)' }}" stroke="var(--sky-c2)" stroke-width="3"/>
                                <text x="{{ $p['x'] }}" y="{{ max(14, $p['y'] - 14) }}" text-anchor="middle" font-size="12" font-family="Roboto Mono, monospace" fill="{{ $isNow ? 'var(--sky-ink)' : 'var(--sky-muted)' }}" font-weight="{{ $isNow ? 600 : 400 }}">{{ $p['value'] > 0 ? fmt_num($p['value'] / 1000).'k' : '–' }}</text>
                            @endif
                            <text x="{{ $p['x'] }}" y="200" text-anchor="middle" font-size="12.5" font-weight="700" fill="{{ $isNow ? 'var(--sky-ink)' : 'var(--sky-muted)' }}" @if ($isNow) text-decoration="underline" @endif>{{ $p['label'] }}</text>
                        </a>
                    @endforeach
                </svg>
            </figure>
            <p class="m-0 text-[12px] text-muted">Click a month to open it.</p>
        </section>

        {{-- Units by product --}}
        <section class="card span-12" aria-labelledby="prod-h">
            <x-card-head id="prod-h" title="Units by product" icon="pill" tone="c3">Sold units, excluding bonus</x-card-head>
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
                <x-empty-state icon="pill" :title="$noSales">{{ $noSalesHint }}</x-empty-state>
            @endif
        </section>

        {{-- Top customers --}}
        <section class="card span-4" aria-labelledby="cust-h">
            <x-card-head id="cust-h" title="Top customers" icon="store" tone="c1">Rs, trade price</x-card-head>
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
            @else
                <x-empty-state icon="store" title="No customer sales yet">The biggest buyers of the month show here.</x-empty-state>
            @endif
        </section>

        {{-- Reps --}}
        <section class="card span-4" aria-labelledby="rep-h">
            <x-card-head id="rep-h" title="Medical rep leaderboard" icon="award" tone="c4" />
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
                <x-empty-state icon="award" title="No rep sales yet">Reps rank here once their customers’ sales are confirmed.</x-empty-state>
            @endif
            @if ($byRep['waiting'] > 0)
                <p class="m-0 rounded-[12px] bg-warn-soft px-3 py-2 text-[13px] text-warn">{{ fmt_rs($byRep['waiting']) }} incentive is waiting on customers with no rep. <a href="{{ route('customers.index', $q + ['status' => 'needs_rep']) }}" class="font-bold text-warn">Assign reps</a></p>
            @endif
        </section>

        {{-- Money --}}
        <section class="card span-4" aria-labelledby="money-h">
            <x-card-head id="money-h" title="Money this month" icon="cash" tone="c6" />
            <dl class="m-0 grid grid-cols-2 gap-2.5">
                @foreach (array_filter([
                    ['Invoiced', fmt_rs($money['invoiced']), 'incl. sales tax and WHT', route('ledger.index', $q)],
                    ['Received', fmt_rs($money['received']), 'payments recorded', route('ledger.index', $q)],
                    ['Invoices issued', fmt_num($money['invoices']), fmt_rs($money['invoicesTotal']), auth()->user()->can('invoices.view') ? route('invoices.index', $q) : null],
                    $money['payroll'] !== null ? ['Net payroll', fmt_rs($money['payroll']), 'base + incentive', route('payroll.index', $q)] : null,
                ]) as [$label, $value, $sub, $url])
                    <div class="flex min-w-0 flex-col gap-1 rounded-2xl bg-sunk px-3.5 py-3">
                        <dt class="text-[12px] font-bold text-muted">{{ $label }}</dt>
                        <dd class="m-0 num truncate text-[18px] font-medium">@if ($url)<a href="{{ $url }}" class="text-ink no-underline hover:underline">{{ $value }}</a>@else{{ $value }}@endif</dd>
                        <dd class="m-0 text-[12px] text-muted">{{ $sub }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-auto flex items-center justify-between gap-3 rounded-2xl bg-soft px-4 py-3 text-softfg">
                <span class="font-bold">Taxes to deposit</span>
                <span class="num text-[18px] font-medium">{{ fmt_rs($t['sales_tax'] + $t['withholding_tax']) }}</span>
            </div>
        </section>

        {{-- Stock --}}
        <section class="card span-12" aria-labelledby="stock-h">
            <x-card-head id="stock-h" title="Stock watch" icon="warehouse" tone="c5">
                <a href="{{ route('inventory.index', $q) }}" class="inline-flex items-center gap-1 font-bold text-accent-text no-underline hover:underline">Inventory <x-icon name="arrow" :size="14" /></a>
            </x-card-head>
            @if ($stock)
                <ul class="m-0 grid list-none gap-x-8 gap-y-3.5 p-0 lg:grid-cols-2">
                    @foreach (array_slice($stock, 0, 6) as $s)
                        @php([$lvl, $tone] = $levels[$s['level']])
                        <li class="flex items-center gap-3">
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="flex items-baseline justify-between gap-2"><b class="truncate">{{ $s['name'] }}</b><span class="num text-[13px] text-muted">{{ fmt_num($s['closing']) }} units</span></span>
                                <span class="bar-track h-2" aria-hidden="true"><span class="block h-full rounded-[7px] bg-{{ $tone }}" style="width: {{ $s['cover'] === null ? 100 : max(3, min(100, round($s['cover'] / 6 * 100))) }}%"></span></span>
                            </span>
                            <span class="w-14 text-right text-[12px] text-muted">{{ $s['cover'] !== null ? fmt_num($s['cover'], 1).' mo' : '–' }}</span>
                            <x-chip :tone="$tone">{{ $lvl }}</x-chip>
                        </li>
                    @endforeach
                </ul>
                <p class="m-0 text-[12px] text-muted">The six products with the least stock left. The bar shows months of cover, full at six months.</p>
            @else
                <x-empty-state icon="warehouse" title="No stock recorded yet" action="Inventory" :href="route('inventory.index', $q)">Post a stock receipt to start tracking cover.</x-empty-state>
            @endif
        </section>
    </div>
</x-layouts.app>
