@php
    $p = $period;
    $state = function ($c) use ($p) {
        if ($c->last_seen->gte($p)) return $c->first_seen->equalTo($p) ? ['New this month', 'info'] : ['Bought this month', 'ok'];
        if ($c->last_seen->gte($p->subMonths(2))) return ['No purchase', 'mute'];
        return ['Inactive', 'bad'];
    };
@endphp
<x-layouts.app title="Customers" description="Customer register built from the distributors' monthly files.">
    <x-page-header title="Customer register" sub="Built from each distributor’s monthly file. New customers are added on confirm; a customer missing from a month is kept and marked, never deleted." />

    <section class="card flex flex-col gap-4" aria-label="Customers">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="flex min-w-60 flex-1 flex-col gap-1.5">
                <label for="q" class="label">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Name, area or customer code" class="field">
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="distributor" class="label">Distributor</label>
                <select id="distributor" name="distributor" class="field w-auto" data-autosubmit>
                    <option value="">All distributors</option>
                    @foreach ($distributors as $d)
                        <option value="{{ $d->id }}" @selected($distributorId === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn">Search</button>
        </form>

        <nav class="tabs" aria-label="Customer status">
            @foreach ($statuses as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" class="tab" @if ($status === $key) aria-current="page" @endif>{{ $label }} · {{ $counts[$key] }}</a>
            @endforeach
        </nav>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Customer</th><th scope="col">Distributor</th><th scope="col" class="r">{{ $period->format('M') }} units</th><th scope="col" class="r">{{ $period->format('M') }} value</th>
                    <th scope="col">Last bought</th><th scope="col">Status</th><th scope="col">Medical rep</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($customers as $c)
                    @php([$label, $tone] = $state($c))
                    <tr>
                        <td><span class="block font-bold">{{ $c->name }}</span><span class="text-[12px] text-muted">{{ $c->code ? 'Code '.$c->code.' · ' : '' }}{{ $c->area ?: 'No area' }}</span></td>
                        <td>{{ $c->distributor->name }}</td>
                        <td class="r">{{ $c->month_units !== null ? fmt_num($c->month_units) : '–' }}</td>
                        <td class="r">{{ $c->month_value !== null ? fmt_rs($c->month_value) : '–' }}</td>
                        <td class="whitespace-nowrap">{{ $c->last_seen->format('M Y') }}</td>
                        <td><x-chip :tone="$tone">{{ $label }}</x-chip></td>
                        <td>
                            @if ($canAssign)
                                <form method="post" action="{{ route('customers.reps', $c) }}" class="flex flex-wrap items-center gap-1.5">
                                    @csrf @method('put')
                                    @php($current = $c->medicalReps->pluck('id')->values())
                                    @foreach ([1, 2] as $slot)
                                        <label for="rep{{ $slot }}-{{ $c->id }}" class="sr-only">{{ $slot === 1 ? 'Medical rep' : 'Second rep (50/50)' }} for {{ $c->name }}</label>
                                        <select id="rep{{ $slot }}-{{ $c->id }}" name="rep_{{ $slot }}" class="field min-h-9 w-auto max-w-48 py-1 text-[13px] {{ $slot === 1 && $current->isEmpty() ? 'border-warn' : '' }}">
                                            <option value="">{{ $slot === 1 ? 'No rep' : '+ second rep' }}</option>
                                            @foreach ($reps as $r)
                                                <option value="{{ $r->id }}" @selected(($current[$slot - 1] ?? null) === $r->id)>{{ $r->name }} · {{ $r->territory->name }}</option>
                                            @endforeach
                                        </select>
                                    @endforeach
                                    <button class="btn btn-sm">Save</button>
                                </form>
                            @else
                                {{ $c->medicalReps->map(fn ($r) => $r->name.($r->pivot->share_percent < 100 ? ' ('.$r->pivot->share_percent.'%)' : ''))->join(', ') ?: 'Not assigned' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty>No customers match. Customers appear here once a distributor file is confirmed.</x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $customers->links() }}
        <p class="text-[12px] text-muted">Customers are matched month to month by the distributor’s customer code, or by name and area where the file prints no codes. Two reps on one customer share its sales and incentive 50/50.</p>
    </section>
</x-layouts.app>
