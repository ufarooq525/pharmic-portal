{{-- Inventory → Stock receipts: every receipt posted, newest first, with revert. --}}
<x-layouts.app title="Inventory · Stock receipts" description="Stock receipts posted to inventory, newest first.">
    @include('sales::_inventory-header', ['tab' => 'receipts', 'sub' => 'Every stock receipt posted, newest first. A wrong receipt can be reverted; it stays here marked “Reverted”.'])

    <section id="receipts" class="card scroll-mt-4" aria-labelledby="rcpt-h">
        <h2 id="rcpt-h" class="card-title card-title-icon"><span class="card-icon" style="--tone: var(--sky-c2)"><x-icon name="truck" /></span>Stock receipts</h2>
        @if ($receipts->isNotEmpty())
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Date</th><th scope="col">Receipt</th><th scope="col" class="r">Products</th><th scope="col" class="r">Units</th><th scope="col">By</th><th scope="col">Status</th>@if ($canRevert)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead>
                    <tbody>
                    @foreach ($receipts as $rc)
                        <tr>
                            <td class="num whitespace-nowrap text-muted">{{ $rc->received_on->format('j M Y') }}</td>
                            <td><span class="block font-semibold">#{{ $rc->id }}{{ $rc->reference ? ' · '.$rc->reference : '' }}</span><span class="text-[12px] text-muted">{{ collect([$rc->supplier, $rc->original_name, $rc->note])->filter()->join(' · ') ?: 'Entered by hand' }}</span></td>
                            <td class="r">{{ $rc->line_count }}</td>
                            <td class="r">{{ fmt_num($rc->total_units) }}</td>
                            <td class="whitespace-nowrap">{{ $rc->createdBy?->name }}</td>
                            <td>
                                @if ($rc->isPosted())
                                    <x-chip tone="ok">Posted</x-chip>
                                @else
                                    <x-chip tone="mute">Reverted {{ $rc->reverted_at?->format('j M') }}</x-chip>
                                @endif
                            </td>
                            @if ($canRevert)
                                <td class="text-right">
                                    @if ($rc->isPosted())
                                        <form method="post" action="{{ route('inventory.receipts.revert', $rc) }}" data-ajax data-confirm="Revert receipt #{{ $rc->id }}? Its {{ fmt_num($rc->total_units) }} units come back out of stock.">
                                            @csrf
                                            <button class="btn btn-sm" data-busy-label="Reverting…">Revert</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $receipts->links() }}
            <p class="m-0 text-[12px] text-muted">A wrong receipt can be reverted: its units come back out of stock and the receipt stays here marked “Reverted”. Reverting is refused if that stock has already been sold.</p>
        @else
            <x-empty>No stock received yet. Opening stock came from the Excel sheet.</x-empty>
        @endif
    </section>
</x-layouts.app>
