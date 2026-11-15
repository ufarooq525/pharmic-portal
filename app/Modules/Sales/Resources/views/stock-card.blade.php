{{-- One product's stock card for the month. Opens in the inventory page's drawer (#stock-card); also works as a page on its own. --}}
@php
    $q = ['period' => $period->format('Y-m')];
@endphp
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock card · {{ $product->name }} · {{ $period->format('F Y') }}</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    @include('partials.view-transition-guard')
    @vite(['resources/css/app.css'])
</head>
<body class="mx-auto flex max-w-[760px] flex-col gap-5 p-6">
<section id="stock-card" class="card flex flex-col gap-4">
    <h2 class="card-title">Stock card · {{ $product->name }}</h2>
    <p class="m-0 text-[13px] text-muted">{{ $period->format('F Y') }}{{ $product->code ? ' · Code '.$product->code : '' }}</p>

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
        @foreach ([['Opening', $card['opening']], ['In', $card['in']], ['Out', $card['out']], ['Closing', $card['closing']]] as [$label, $value])
            <div class="flex flex-col gap-0.5 rounded-2xl bg-sunk px-3.5 py-2.5">
                <span class="text-[12px] font-bold text-muted">{{ $label }}</span>
                <span class="num text-[18px] font-semibold">{{ fmt_num($value) }}</span>
            </div>
        @endforeach
    </div>

    @if ($card['rows'])
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th scope="col">Date</th><th scope="col">Movement</th><th scope="col" class="r">In</th><th scope="col" class="r">Out</th><th scope="col" class="r">Balance</th></tr></thead>
                <tbody>
                <tr><td class="num text-muted">1 {{ $period->format('M') }}</td><td class="font-semibold">Opening balance</td><td class="r"></td><td class="r"></td><td class="r font-bold">{{ fmt_num($card['opening']) }}</td></tr>
                @foreach ($card['rows'] as $r)
                    <tr>
                        <td class="num whitespace-nowrap text-muted">{{ $r['date']->format('j M') }}</td>
                        <td><span class="font-semibold">{{ $r['type']?->label() ?? 'Movement' }}</span>@if ($r['detail'])<span class="block text-[12px] text-muted">{{ $r['detail'] }}</span>@endif</td>
                        <td class="r text-ok">{{ $r['units'] > 0 ? fmt_num($r['units']) : '' }}</td>
                        <td class="r">{{ $r['units'] < 0 ? fmt_num(-$r['units']) : '' }}</td>
                        <td class="r font-bold">{{ fmt_num($r['balance']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty>No stock moved for {{ $product->name }} in {{ $period->format('F Y') }}. Closing stock is the opening balance.</x-empty>
    @endif

    <div class="flex flex-wrap gap-2">
        @if ($canReceive)
            <a href="{{ route('inventory.index', $q + ['product' => $product->id]) }}#receive" data-drawer="#receive" data-drawer-title="Receive stock" class="btn btn-primary btn-sm"><x-icon name="plus" :size="16" /> Receive stock</a>
        @endif
        @if ($canEditRates)
            <a href="{{ route('products.index', $q + ['edit' => $product->id]) }}#rate-form" data-drawer="#rate-form" data-drawer-title="Rates for {{ $product->name }}" class="btn btn-sm"><x-icon name="pencil" :size="16" /> Edit rates and taxes</a>
        @endif
    </div>
</section>
</body>
</html>
