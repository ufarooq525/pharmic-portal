@php
    $format = fn ($value, $kind) => match (true) {
        $value === null || $value === '' => '–',
        $kind === 'money' => fmt_num($value),
        $kind === 'num' => fmt_num($value),
        $kind === 'pct' => fmt_pct($value),
        default => $value,
    };
    $numeric = fn ($kind) => in_array($kind, ['num', 'money', 'pct'], true);
    $tabs = ['sales' => 'Sales', 'customers' => 'Customer-wise', 'bonus' => 'Bonus', 'incentive' => 'Incentive', 'taxes' => 'Sales tax & WHT'];
@endphp
<x-layouts.app :title="$titles[$type]" description="{{ $titles[$type] }} for {{ $period->format('F Y') }}, confirmed imports only.">
    <x-page-header title="Reports" sub="{{ $period->format('F Y') }}, confirmed imports only. Money in rupees.">
        <a href="{{ route('reports.export', ['type' => $type] + request()->query()) }}" class="btn">Export CSV</a>
    </x-page-header>

    <nav class="tabs" aria-label="Reports">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('reports.show', ['type' => $key] + request()->query()) }}" class="tab" @if ($type === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <section class="card flex flex-col gap-4" aria-labelledby="rep-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="rep-h" class="card-title">{{ $titles[$type] }}</h2>
            <form method="get" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                @foreach ([['distributor', 'Distributor', $distributors], ['product', 'Product', $products], ['rep', 'Medical rep', $reps]] as [$name, $label, $options])
                    <div class="flex flex-col gap-1">
                        <label for="f-{{ $name }}" class="label">{{ $label }}</label>
                        <select id="f-{{ $name }}" name="{{ $name }}" class="field min-h-10 w-auto py-1.5" data-autosubmit>
                            <option value="">All</option>
                            @foreach ($options as $o)
                                <option value="{{ $o->id }}" @selected($filters[$name] === $o->id)>{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <noscript><button class="btn btn-sm">Apply</button></noscript>
            </form>
        </div>

        @if ($rows->isNotEmpty())
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr>
                        @foreach ($columns as [$label, $kind])
                            <th scope="col" @class(['r' => $numeric($kind)])>{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $key => [$label, $kind])
                                <td @class(['r' => $numeric($kind), 'font-semibold' => $loop->first])>{{ $format($row[$key] ?? null, $kind) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        @foreach ($columns as $key => [$label, $kind])
                            <td @class(['r' => $numeric($kind)])>{{ $loop->first ? ($rows->hasPages() ? 'Total, all pages' : 'Total') : ($totals[$key] !== null ? $format($totals[$key], $kind) : '') }}</td>
                        @endforeach
                    </tr>
                    </tfoot>
                </table>
            </div>
            {{ $rows->links() }}
        @else
            <x-empty>No confirmed sales match these filters for {{ $period->format('F Y') }}.</x-empty>
        @endif

        @if ($type === 'incentive')
            <p class="text-[12px] text-muted">Incentive = sales at Ex-FP × the product’s incentive %, split by each customer’s rep share. Rates are copied in when the import is confirmed, so later changes never alter this month.</p>
        @elseif ($type === 'bonus')
            <p class="text-[12px] text-muted">Where the distributor’s file has a bonus column, its figure is used; otherwise the product’s scheme (for example 10+2) is applied pro rata.</p>
        @elseif ($type === 'taxes')
            <p class="text-[12px] text-muted">Both taxes are worked out on the trade-price value at each product’s own rate for the month.</p>
        @endif
    </section>
</x-layouts.app>
