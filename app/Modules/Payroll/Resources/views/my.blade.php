<x-layouts.app title="My territory" description="Your customers, sales, incentive and payslip.">
    <x-page-header :title="$rep->name.' · '.$rep->territory->name" sub="Your customers and confirmed {{ $period->format('F Y') }} sales. A shared customer counts at your share.">
        <a href="{{ route('payroll.payslip', [$rep, 'period' => $period->format('Y-m')]) }}" target="_blank" rel="noopener" class="btn btn-primary">My payslip</a>
    </x-page-header>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3.5">
        <x-kpi label="Sales (Ex-FP)" :value="fmt_rs($customers->sum('sales'))" :note="$customers->where('units', '>', 0)->count().' customers bought'" />
        <x-kpi label="Units + bonus" :value="fmt_num($customers->sum('units')).' + '.fmt_num($customers->sum('bonus'))" />
        <x-kpi label="Incentive" :value="fmt_rs($customers->sum('incentive'))" note="this month" />
        <x-kpi label="Net pay" :value="fmt_rs($pay['net'] ?? 0)" note="base + incentive" />
    </div>

    <section class="card" aria-labelledby="my-h">
        <h2 id="my-h" class="card-title">My customers</h2>
        @if ($customers->isNotEmpty())
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Customer</th><th scope="col">Distributor</th><th scope="col" class="r">Share</th><th scope="col" class="r">Units</th><th scope="col" class="r">Bonus</th><th scope="col" class="r">Sales Rs</th><th scope="col" class="r">Incentive Rs</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $c)
                        <tr>
                            <td><span class="block font-semibold">{{ $c->name }}</span><span class="text-[12px] text-muted">{{ $c->area ?: 'No area' }}</span></td>
                            <td>{{ $c->distributor }}</td>
                            <td class="r">{{ (int) $c->share_percent }}%</td>
                            <td class="r">{{ fmt_num($c->units) }}</td>
                            <td class="r">{{ fmt_num($c->bonus) }}</td>
                            <td class="r">{{ fmt_num($c->sales) }}</td>
                            <td class="r">{{ fmt_num($c->incentive) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty>No customers are linked to you yet. Ask Accounts to link your customers.</x-empty>
        @endif
    </section>
</x-layouts.app>
