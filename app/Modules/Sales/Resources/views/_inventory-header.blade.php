{{-- Inventory banner and tabs, shared by Stock, Products & prices and Stock receipts. --}}
@php($canReceiveStock = auth()->user()->can('stock.receive'))
<x-page-header title="Inventory" :sub="$sub">
    @if ($canReceiveStock)
        <a href="{{ route('inventory.index', ['period' => $period->format('Y-m')]) }}#receive" data-drawer="#receive" data-drawer-title="Receive stock" class="btn btn-primary">+ Receive stock</a>
    @endif
</x-page-header>
<x-page-tabs page="inventory" :current="$tab" />
