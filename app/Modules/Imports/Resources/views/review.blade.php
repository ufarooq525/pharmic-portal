@php
    $t = $totals;
    $statusChip = ['confirmed' => ['Confirmed', 'ok'], 'needs_review' => ['Needs review', 'warn'], 'blocked' => ['Blocked', 'bad']][$import->status];
    $attention = collect($customers)->where('attention', true)->count();
    $layouts = ['matrix' => 'Product grid (SoftTouch)', 'lineitem' => 'Line items with bonus', 'monthly' => 'Monthly columns'];
@endphp
<x-layouts.app title="{{ $import->distributor->name }} · {{ $period->format('F Y') }}" description="Review of the customer-wise file from {{ $import->distributor->name }} for {{ $period->format('F Y') }}.">
    <x-page-header :title="$import->distributor->name.' · '.$period->format('F Y')">
        <x-chip :tone="$statusChip[1]" class="text-[13px]">{{ $statusChip[0] }}</x-chip>
        <a href="{{ route('imports.file', $import) }}" class="btn btn-sm">Open the PDF</a>
        <a href="{{ route('imports.index', ['period' => $period->format('Y-m')]) }}" class="btn btn-sm">All files</a>
    </x-page-header>
    <p class="-mt-3 text-muted">{{ $import->original_name }} · {{ $layouts[$import->layout] ?? $import->layout }} · header reads “{{ $import->distributor_in_file }}”</p>

    <ul class="grid gap-2.5 sm:grid-cols-3" aria-label="Checks">
        @foreach ($import->checks as $check)
            <li class="flex items-start gap-2.5 rounded-2xl px-4 py-3 font-semibold {{ $check['ok'] ? 'bg-ok-soft text-ok' : 'bg-bad-soft text-bad' }}">
                <span aria-hidden="true" class="font-extrabold">{{ $check['ok'] ? '✓' : '✕' }}</span>
                <span><span class="sr-only">{{ $check['ok'] ? 'Passed:' : 'Failed:' }}</span> {{ $check['message'] }}</span>
            </li>
        @endforeach
    </ul>

    @if ($unmapped)
        <section class="card flex flex-col gap-3 bg-warn-soft" aria-labelledby="map-h">
            <h2 id="map-h" class="card-title text-warn">Link {{ count($unmapped) }} product {{ str('name')->plural(count($unmapped)) }}</h2>
            <p>This distributor spells these products in a way the portal has not seen before. Link each one once; later files are recognised automatically.</p>
            @foreach ($unmapped as $name)
                @if ($canMap)
                    <form method="post" action="{{ route('imports.map', $import) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="product_name" value="{{ $name }}">
                        <span class="min-w-48 font-bold">“{{ $name }}”</span>
                        <label for="map-{{ $loop->index }}" class="sr-only">Product for {{ $name }}</label>
                        <select id="map-{{ $loop->index }}" name="product_id" required class="field w-auto min-w-64 bg-surface">
                            <option value="">Choose the product…</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn">Link</button>
                    </form>
                @else
                    <p class="font-bold">“{{ $name }}”</p>
                @endif
            @endforeach
        </section>
    @endif

    <div class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-3">
        <x-kpi label="Customers" :value="fmt_num(count($customers))" :note="$newCustomers ? $newCustomers.' new to the register' : null" />
        <x-kpi label="Units + bonus" :value="fmt_num($t['units']).' + '.fmt_num($t['bonus'])" />
        <x-kpi label="Value (trade price)" :value="fmt_rs($t['value'])" />
        <x-kpi label="Sales tax" :value="fmt_rs($t['sales_tax'])" />
        <x-kpi label="Withholding tax" :value="fmt_rs($t['withholding_tax'])" />
    </div>

    <section class="card flex flex-col gap-4" aria-labelledby="cust-h">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="cust-h" class="card-title">Customer-wise data</h2>
            <nav class="tabs" aria-label="Filter customers">
                <a href="{{ request()->fullUrlWithQuery(['show' => null]) }}" class="tab" @if (! $attentionOnly) aria-current="page" @endif>All · {{ count($customers) }}</a>
                <a href="{{ request()->fullUrlWithQuery(['show' => 'attention']) }}" class="tab" @if ($attentionOnly) aria-current="page" @endif>Needs attention · {{ $attention }}</a>
            </nav>
        </div>

        <div class="table-wrap">
            <div class="min-w-[880px]">
                <div class="grid grid-cols-[minmax(220px,2.3fr)_minmax(150px,1.3fr)_70px_70px_110px_90px_90px_130px] gap-3 rounded-xl bg-sunk px-3 py-2.5 text-[12px] font-bold text-muted" aria-hidden="true">
                    <span>Customer</span><span>Medical rep</span><span class="text-right">Units</span><span class="text-right">Bonus</span><span class="text-right">Value Rs</span><span class="text-right">Sales tax</span><span class="text-right">WHT</span><span>Status</span>
                </div>
                @forelse ($shown as $c)
                    <details class="group border-b border-line">
                        <summary class="grid cursor-pointer list-none grid-cols-[minmax(220px,2.3fr)_minmax(150px,1.3fr)_70px_70px_110px_90px_90px_130px] items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-sunk group-open:bg-accent-soft/50 [&::-webkit-details-marker]:hidden">
                            <span class="min-w-0"><span class="block font-bold">{{ $c['name'] }}</span><span class="text-[12px] text-muted">{{ $c['code'] ? 'Code '.$c['code'].' · ' : '' }}{{ $c['area'] ?: 'No area' }}</span></span>
                            <span class="text-[13px] {{ $c['reps'] ? '' : 'text-warn' }}">{{ $c['reps'] ?: 'Not assigned' }}</span>
                            <span class="num text-right">{{ fmt_num($c['units']) }}</span>
                            <span class="num text-right">{{ fmt_num($c['bonus']) }}</span>
                            <span class="num text-right">{{ fmt_num($c['value']) }}</span>
                            <span class="num text-right">{{ fmt_num($c['sales_tax']) }}</span>
                            <span class="num text-right">{{ fmt_num($c['withholding_tax']) }}</span>
                            <span><x-chip :tone="$c['tone']">{{ $c['status'] }}</x-chip></span>
                        </summary>
                        <div class="mb-2 ml-6 rounded-xl bg-sunk px-3 py-1">
                            @forelse ($c['lines'] as $l)
                                <div class="grid grid-cols-[minmax(170px,1.5fr)_minmax(140px,1.3fr)_60px_60px_100px_120px_120px_minmax(150px,1.3fr)] gap-3 border-b border-line py-2 text-[13px] last:border-0 {{ $l['product'] ? '' : 'text-bad' }}">
                                    <span class="font-mono text-[12px]">{{ $l['name_in_file'] }}</span>
                                    <span class="font-semibold">{{ $l['product'] ?? 'Not linked' }}</span>
                                    <span class="num text-right">{{ fmt_num($l['units']) }}</span>
                                    <span class="num text-right">{{ fmt_num($l['bonus']) }}</span>
                                    <span class="num text-right">{{ fmt_num($l['value']) }}</span>
                                    <span class="num text-right">{{ $l['sales_tax_rate'] !== null ? 'ST '.fmt_pct($l['sales_tax_rate']).' · '.fmt_num($l['sales_tax']) : '–' }}</span>
                                    <span class="num text-right">{{ $l['withholding_tax_rate'] !== null ? 'WHT '.fmt_pct($l['withholding_tax_rate']).' · '.fmt_num($l['withholding_tax']) : '–' }}</span>
                                    <span class="text-[12px] text-muted">{{ $l['note'] }}</span>
                                </div>
                            @empty
                                <p class="py-2 text-[13px] text-muted">Listed in the file with no product lines.</p>
                            @endforelse
                        </div>
                    </details>
                @empty
                    <x-empty class="mt-2">Every customer in this file is ready.</x-empty>
                @endforelse
            </div>
        </div>
        <p class="text-[12px] text-muted">Click a customer to see its product lines. Where the file has no bonus column, bonus follows the product’s scheme. Taxes use each product’s rates for {{ $period->format('F Y') }}.</p>
    </section>

    <div class="sticky bottom-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-ink px-5 py-3.5 text-bg">
        <span>{{ fmt_num($t['units']) }} units + {{ fmt_num($t['bonus']) }} bonus leave stock · {{ fmt_rs($t['value']) }} sales · ST {{ fmt_rs($t['sales_tax']) }} · WHT {{ fmt_rs($t['withholding_tax']) }}</span>
        @if ($canConfirm)
            <form method="post" action="{{ route('imports.confirm', $import) }}">
                @csrf
                <button class="btn border-transparent bg-accent-soft text-accent-strong hover:bg-surface">Confirm and post</button>
            </form>
        @elseif ($canReverse)
            <form method="post" action="{{ route('imports.reverse', $import) }}">
                @csrf
                <button class="btn border-transparent bg-bad-soft text-bad">Undo this import</button>
            </form>
        @else
            <span class="font-bold">
                @if ($import->isConfirmed()) Confirmed {{ $import->confirmed_at?->format('j M Y, g:i a') }}
                @elseif ($import->status === 'blocked') Blocked: fix the failed check or upload the right file
                @elseif ($unmapped) Link every product before confirming
                @else Waiting for Accounts to confirm
                @endif
            </span>
        @endif
    </div>
</x-layouts.app>
