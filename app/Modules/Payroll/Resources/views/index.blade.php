@php
    $r = collect($rows);
    $page = \App\Support\Paginate::collection($r, 25);
    $q = ['period' => $period->format('Y-m')];
    // Month filter: every month with payroll, plus the one on screen (and the current month, to start a new one).
    $current = $period->format('Y-m');
    $monthOptions = $months->keyBy(fn ($m) => $m->format('Y-m'));
    $hasPay = $monthOptions->has($current);
    $monthOptions->put($current, $period);
    $monthOptions->put(now()->format('Y-m'), \Carbon\CarbonImmutable::now()->startOfMonth());
    $monthOptions = $monthOptions->sortKeysDesc();
    $prev = $period->subMonthNoOverflow()->format('Y-m');
    $next = $period->addMonthNoOverflow()->format('Y-m');
    $latest = $months->first();
    $isEmpty = $r->sum('sales') == 0 && $r->sum('allowance') == 0 && $r->sum('deductions') == 0;
@endphp
<x-layouts.app title="MR payroll" description="Base salary plus the percentage incentive each medical rep earned from confirmed sales.">
    <x-page-header title="MR payroll" sub="Base salary plus the percentage incentive each medical rep earned from confirmed {{ $period->format('F') }} sales. A shared customer’s incentive is split by its share." />

    {{-- Month filter: pick any month that has payroll, or step back and forward a month --}}
    <form method="get" action="{{ route('payroll.index') }}" class="toolbar" aria-label="Payroll month">
        <div class="flex min-w-0 flex-col gap-1 max-sm:w-full">
            <label for="pay-month" class="label">Payroll for</label>
            <div class="flex min-w-0 items-center gap-1.5">
                <a href="{{ route('payroll.index', ['period' => $prev]) }}" class="btn btn-icon" aria-label="Previous month, {{ $period->subMonthNoOverflow()->format('F Y') }}" title="Previous month">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </a>
                <select id="pay-month" name="period" class="field min-h-11 w-auto min-w-0 flex-1 sm:min-w-[230px] sm:flex-none" data-autosubmit>
                    @foreach ($monthOptions as $key => $m)
                        <option value="{{ $key }}" @selected($key === $current)>{{ $m->format('F Y') }}{{ $months->contains(fn ($x) => $x->format('Y-m') === $key) ? '' : ' · nothing confirmed yet' }}</option>
                    @endforeach
                </select>
                <a href="{{ route('payroll.index', ['period' => $next]) }}" class="btn btn-icon" aria-label="Next month, {{ $period->addMonthNoOverflow()->format('F Y') }}" title="Next month">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            </div>
        </div>
        <noscript><button class="btn">Show</button></noscript>
        @if ($months->count())
            <p class="m-0 self-center text-[13px] text-muted">{{ $months->count() }} {{ str('month')->plural($months->count()) }} with payroll, latest {{ $latest->format('F Y') }}.</p>
        @endif
    </form>

    @if ($isEmpty)
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-dashed border-line bg-surface px-4 py-3.5" role="note">
            <span class="flex size-9 flex-none items-center justify-center rounded-full bg-warn-soft text-warn"><x-icon name="alert" /></span>
            <p class="m-0 min-w-0 flex-1">
                <b>No confirmed sales in {{ $period->format('F Y') }} yet</b>, so there is no incentive to pay for this month.
                <span class="text-muted">Incentive comes from distributor files once they are confirmed under Imports.</span>
            </p>
            @if ($latest && $latest->format('Y-m') !== $current)
                <a href="{{ route('payroll.index', ['period' => $latest->format('Y-m')]) }}" class="btn btn-primary btn-sm">Open {{ $latest->format('F Y') }} payroll</a>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3.5">
        <x-kpi label="Base salaries" :value="fmt_rs($r->sum('base'))" :note="$r->count().' medical reps'" />
        <x-kpi label="Incentive" :value="fmt_rs($r->sum('incentive'))" :note="'from '.$period->format('F').' sales'" />
        <x-kpi label="Net payroll" :value="fmt_rs($r->sum('net'))" note="to pay" />
    </div>

    <section class="card" aria-labelledby="pay-h">
        <h2 id="pay-h" class="card-title card-title-icon"><span class="card-icon" style="--tone: var(--sky-c4)"><x-icon name="wallet" /></span>{{ $period->format('F Y') }} payroll</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Medical rep</th><th scope="col">Territory</th><th scope="col" class="r">Sales Ex-FP</th><th scope="col" class="r">Base salary</th><th scope="col" class="r">Incentive</th>
                    <th scope="col" class="r">Allowance</th><th scope="col" class="r">Deductions</th><th scope="col" class="r">Net pay</th><th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($page as $row)
                    @php($rep = $row['rep'])
                    <tr>
                        <td class="font-bold">{{ $rep->name }}@if ($row['note'])<span class="block text-[12px] font-normal text-muted">{{ $row['note'] }}</span>@endif</td>
                        <td>{{ $rep->territory->name }}</td>
                        <td class="r">{{ fmt_num($row['sales']) }}</td>
                        <td class="r">{{ fmt_num($row['base']) }}</td>
                        <td class="r">{{ fmt_num($row['incentive']) }}</td>
                        <td class="r">{{ fmt_num($row['allowance']) }}</td>
                        <td class="r">{{ fmt_num($row['deductions']) }}</td>
                        <td class="r font-extrabold">{{ fmt_num($row['net']) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('payroll.payslip', [$rep] + $q) }}" data-drawer="#payslip" data-drawer-title="Payslip · {{ $rep->name }}" class="btn btn-sm" aria-label="Payslip for {{ $rep->name }}" data-row-target>Payslip</a>
                            <a href="{{ whatsapp_url($rep->phone, config('pharmic.company')." payslip, {$period->format('F Y')}\n{$rep->name} · {$rep->territory->name}\nBase ".fmt_rs($row['base']).' + incentive '.fmt_rs($row['incentive']).($row['allowance'] ? ' + allowance '.fmt_rs($row['allowance']) : '').($row['deductions'] ? ' − deductions '.fmt_rs($row['deductions']) : '')."\nNet pay: ".fmt_rs($row['net'])) }}" target="_blank" rel="noopener" class="btn btn-sm">WhatsApp</a>
                            @if ($canEdit)<a href="{{ route('payroll.index', $q + ['edit' => $rep->id]) }}#adjust" data-drawer="#adjust" class="btn btn-sm">Adjust</a>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr><td>Total</td><td></td><td class="r">{{ fmt_num($r->sum('sales')) }}</td><td class="r">{{ fmt_num($r->sum('base')) }}</td><td class="r">{{ fmt_num($r->sum('incentive')) }}</td><td class="r">{{ fmt_num($r->sum('allowance')) }}</td><td class="r">{{ fmt_num($r->sum('deductions')) }}</td><td class="r">{{ fmt_num($r->sum('net')) }}</td><td></td></tr>
                </tfoot>
            </table>
        </div>
        {{ $page->links() }}
        <p class="m-0 text-[12px] text-muted">Incentive is each product’s incentive % on the Ex-FP value of confirmed sales to the rep’s customers. Base salaries and phone numbers are set under Master data → Medical reps.</p>
    </section>

    @if ($canEdit && ($row = $r->first(fn ($x) => $x['rep']->id === $editing)))
        <section id="adjust" class="card scroll-mt-4" aria-labelledby="adj-h" data-drawer-source>
            <h2 id="adj-h" class="card-title">Adjust {{ $row['rep']->name }}’s {{ $period->format('F') }} pay</h2>
            <form method="post" action="{{ route('payroll.adjust', $row['rep']) }}" class="grid gap-4 sm:grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                @csrf
                @method('put')
                <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                <div class="flex flex-col gap-1.5">
                    <label for="adj-a" class="label">Allowance, Rs</label>
                    <input id="adj-a" name="allowance" type="number" min="0" step="1" value="{{ old('allowance', $row['allowance']) }}" class="field num">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="adj-d" class="label">Deductions, Rs</label>
                    <input id="adj-d" name="deductions" type="number" min="0" step="1" value="{{ old('deductions', $row['deductions']) }}" class="field num">
                </div>
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label for="adj-n" class="label">Note (shows on the payslip)</label>
                    <input id="adj-n" name="note" maxlength="255" value="{{ old('note', $row['note']) }}" class="field" placeholder="e.g. Advance recovered">
                </div>
                <div class="flex gap-2 sm:col-span-full">
                    <button class="btn btn-primary">Save</button>
                    <button type="button" class="btn" data-drawer-close>Cancel</button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.app>
