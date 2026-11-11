@php
    $lines = $invoice->lines->map->toArray()->all();
    $totals = $invoice->only(['units', 'bonus', 'value', 'sales_tax', 'withholding_tax', 'total']);
    $title = ($invoice->isCreditNote() ? 'Credit note ' : 'Invoice ').$invoice->number;
@endphp
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('pharmic.company') }}</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    @vite(['resources/css/app.css'])
    <style>@media print { .no-print { display: none !important; } body { background: #fff; } .card { box-shadow: none; padding: 0; } }</style>
</head>
<body class="mx-auto flex max-w-[1000px] flex-col gap-5 p-6">
    <div class="no-print flex gap-2">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / save as PDF</button>
    </div>
    <main class="card gap-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <span class="text-[20px] font-extrabold">{{ config('pharmic.company') }}</span>
                @foreach (array_filter([config('pharmic.address'), config('pharmic.phone'), config('pharmic.ntn') ? 'NTN '.config('pharmic.ntn') : null, config('pharmic.strn') ? 'STRN '.config('pharmic.strn') : null]) as $line)
                    <span class="text-muted">{{ $line }}</span>
                @endforeach
            </div>
            <div class="flex flex-col items-end gap-1 text-right">
                <span class="label">{{ $invoice->isCreditNote() ? 'Credit note' : 'Invoice' }}{{ $invoice->status === 'cancelled' ? ' · Cancelled' : '' }}</span>
                <span class="num text-[22px] font-extrabold">{{ $invoice->number }}</span>
                <span class="text-muted">Issued {{ $invoice->issued_at?->format('j M Y') }}</span>
            </div>
        </div>
        <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
            <dt class="text-muted">Bill to</dt><dd class="m-0 font-bold">{{ $invoice->billTo() }}</dd>
            <dt class="text-muted">Account</dt><dd class="m-0">{{ $invoice->customer ? 'Customer of '.$invoice->distributor->name : 'Distributor account' }}</dd>
            <dt class="text-muted">Period</dt><dd class="m-0">{{ $invoice->period->format('F Y') }}</dd>
            @if ($invoice->creditNoteFor)
                <dt class="text-muted">Reverses</dt><dd class="m-0">{{ $invoice->creditNoteFor->number }} · {{ $invoice->cancel_reason }}</dd>
            @endif
        </dl>
        @include('billing::_lines')
        <div class="flex justify-end text-[22px] font-extrabold">Total {{ fmt_rs($invoice->total) }}</div>
        <p class="m-0 text-[12px] text-muted">Bonus units are at zero value. Returns reduce the invoice. Sales tax and withholding tax are worked out per product at the rate in force for {{ $invoice->period->format('F Y') }}.</p>
    </main>
</body>
</html>
