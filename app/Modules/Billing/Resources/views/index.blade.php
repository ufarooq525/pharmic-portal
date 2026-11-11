@php
    $q = ['period' => $period->format('Y-m')];
    $billTo = $customer ? $customer->name.($customer->area ? ', '.$customer->area : '') : ($distributor ? $distributor->name.', '.$distributor->city : null);
    $kinds = ['invoice' => 'Invoice', 'credit_note' => 'Credit note'];
@endphp
<x-layouts.app title="Invoices" description="Invoices for a distributor or a single customer from confirmed sales, with sales tax and withholding tax.">
    <x-page-header title="Invoices" sub="Pick a distributor or any single customer. Lines, bonus, sales tax and withholding tax fill in from confirmed sales." />

    <form method="get" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
        <div class="flex flex-col gap-1.5">
            <label for="bill-to" class="label">Bill to</label>
            <select id="bill-to" name="to" class="field w-auto min-w-[320px]" data-autosubmit>
                <option value="">Choose a distributor or customer…</option>
                <optgroup label="Distributor accounts">
                    @foreach ($distributors as $d)
                        <option value="d:{{ $d->id }}" @selected($to === 'd:'.$d->id)>{{ $d->name }}, {{ $d->city }}</option>
                    @endforeach
                </optgroup>
                @foreach ($distributors as $d)
                    @if ($customers->has($d->id))
                        <optgroup label="Customers of {{ $d->name }}">
                            @foreach ($customers[$d->id] as $c)
                                <option value="c:{{ $c->id }}" @selected($to === 'c:'.$c->id)>{{ $c->name }}{{ $c->area ? ' · '.$c->area : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
        </div>
        <noscript><button class="btn">Show</button></noscript>
    </form>

    @if ($distributor)
        <section class="card gap-5" aria-labelledby="doc-h">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <span class="label">{{ $issued ? ($issued->status === 'cancelled' ? 'Cancelled invoice' : 'Invoice') : 'Draft, not issued yet' }}</span>
                    <h2 id="doc-h" class="m-0 text-[24px] font-extrabold tracking-[-0.02em]">{{ $issued?->number ?? 'INV-'.$period->format('ym').'-····' }}</h2>
                    <span class="text-muted">{{ config('pharmic.company') }}{{ config('pharmic.ntn') ? ' · NTN '.config('pharmic.ntn') : '' }}{{ config('pharmic.strn') ? ' · STRN '.config('pharmic.strn') : '' }}</span>
                </div>
                <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-[14px]">
                    <dt class="text-muted">Bill to</dt><dd class="m-0 font-bold">{{ $billTo }}</dd>
                    <dt class="text-muted">Account</dt><dd class="m-0">{{ $customer ? 'Customer of '.$distributor->name : 'Distributor account' }}</dd>
                    <dt class="text-muted">Period</dt><dd class="m-0">{{ $period->format('F Y') }}</dd>
                    @if ($issued)
                        <dt class="text-muted">Issued</dt><dd class="m-0">{{ $issued->issued_at->format('j M Y, g:i a') }} by {{ $issued->issuedBy?->name }}</dd>
                    @endif
                </dl>
            </div>

            @if ($lines)
                @include('billing::_lines')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-soft px-[18px] py-3.5 text-softfg">
                    <span class="font-semibold">Bonus units are at zero value. Returns reduce the invoice. An issued invoice is locked; corrections go through a credit note.</span>
                    <span class="text-[22px] font-extrabold">{{ fmt_rs($totals['total']) }}</span>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if (! $issued && $canIssue)
                        <form method="post" action="{{ route('invoices.store') }}">
                            @csrf
                            <input type="hidden" name="to" value="{{ $to }}">
                            <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                            <button class="btn btn-primary" data-busy-label="Issuing…">Issue invoice</button>
                        </form>
                    @endif
                    @if ($issued)
                        <a href="{{ route('invoices.print', $issued) }}" target="_blank" rel="noopener" class="btn btn-primary">Print / save as PDF</a>
                        <a href="{{ whatsapp_url($customer ? null : $distributor->phone, config('pharmic.company')." invoice {$issued->number}\nBill to: {$billTo}\nPeriod: ".$period->format('F Y')."\nUnits ".fmt_num($issued->units).' + bonus '.fmt_num($issued->bonus)."\nTotal: ".fmt_rs($issued->total)) }}" target="_blank" rel="noopener" class="btn">Share on WhatsApp</a>
                        @if ($canCancel && $issued->status === 'issued')
                            <details class="w-full">
                                <summary class="btn btn-sm cursor-pointer">Cancel with a credit note</summary>
                                <form method="post" action="{{ route('invoices.cancel', $issued) }}" class="mt-3 flex flex-wrap items-end gap-2" data-confirm="Cancel {{ $issued->number }}? A credit note will reverse it in full.">
                                    @csrf
                                    <div class="flex flex-col gap-1.5">
                                        <label for="cn-reason" class="label">Reason</label>
                                        <input id="cn-reason" name="reason" required maxlength="255" class="field min-w-[320px]" placeholder="e.g. Import was undone and re-posted">
                                    </div>
                                    <button class="btn border-transparent bg-bad text-surface">Cancel invoice</button>
                                </form>
                            </details>
                        @endif
                    @endif
                </div>
                @if (! $issued)
                    <p class="m-0 text-[12px] text-muted">This is a draft from the confirmed sales. Issuing gives it a number and freezes it, so later changes to rates or imports do not change it.</p>
                @endif
            @else
                <x-empty>No confirmed sales for {{ $billTo }} in {{ $period->format('F Y') }}.</x-empty>
            @endif
        </section>
    @endif

    <section class="card" aria-labelledby="list-h">
        <h2 id="list-h" class="card-title">Issued in {{ $period->format('F Y') }}</h2>
        @if ($month->isNotEmpty())
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Number</th><th scope="col">Type</th><th scope="col">Bill to</th><th scope="col" class="r">Total Rs</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                    @foreach ($month as $inv)
                        <tr>
                            <td class="num font-semibold">{{ $inv->number }}</td>
                            <td>{{ $kinds[$inv->kind] }}</td>
                            <td>{{ $inv->billTo() }}</td>
                            <td class="r">{{ fmt_num($inv->total) }}</td>
                            <td><x-chip :tone="$inv->status === 'cancelled' ? 'mute' : ($inv->kind === 'credit_note' ? 'info' : 'ok')">{{ $inv->status === 'cancelled' ? 'Cancelled' : 'Issued' }}</x-chip></td>
                            <td class="text-right"><a href="{{ route('invoices.print', $inv) }}" target="_blank" rel="noopener" class="btn btn-sm">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty>No invoices issued for {{ $period->format('F Y') }} yet.</x-empty>
        @endif
    </section>
</x-layouts.app>
