@php
    $sum = fn ($k) => $rows->sum($k);
    $levels = ['out' => ['Out of stock', 'bad'], 'low' => ['Reorder', 'warn'], 'ok' => ['OK', 'ok']];
@endphp
<x-layouts.app title="Inventory" description="Stock movement and closing stock for {{ $period->format('F Y') }}.">
    <x-page-header title="Inventory" sub="Stock for {{ $period->format('F Y') }}. Stock receipts add units; confirmed imports take out units sold plus bonus units; returns add stock back. Opening stock comes from the client’s Excel sheet.">
        @if ($canReceive)
            <a href="#receive" data-drawer="#receive" class="btn btn-primary">+ Receive stock</a>
        @endif
    </x-page-header>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3.5">
        <x-kpi label="Closing units" :value="fmt_num($sum('closing'))" note="all products" />
        <x-kpi label="Value at cost" :value="fmt_rs($sum('value_cost'))" note="closing stock" />
        <x-kpi label="Value at Ex-FP" :value="fmt_rs($sum('value_exfp'))" note="closing stock" />
        <x-kpi label="Need reordering" :value="fmt_num($rows->where('level', '!=', 'ok')->count())" note="under 1.5 months of cover" />
    </div>

    <section class="card flex flex-col gap-4" aria-labelledby="inv-h">
        <h2 id="inv-h" class="card-title">Stock by product</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Product</th><th scope="col" class="r">Opening</th><th scope="col" class="r">Received</th><th scope="col" class="r">Sold</th><th scope="col" class="r">Bonus</th>
                    <th scope="col" class="r">Returns</th><th scope="col" class="r">Closing</th><th scope="col" class="r">Value at cost</th><th scope="col" class="r">Value at Ex-FP</th>
                    <th scope="col" class="r">Sales tax</th><th scope="col" class="r">WHT</th><th scope="col" class="r">Lasts</th><th scope="col">Stock</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-bold">{{ $r['name'] }}</td>
                        <td class="r">{{ fmt_num($r['opening']) }}</td>
                        <td class="r">{{ fmt_num($r['purchases']) }}</td>
                        <td class="r">{{ fmt_num($r['sold']) }}</td>
                        <td class="r">{{ fmt_num($r['bonus']) }}</td>
                        <td class="r">{{ fmt_num($r['returned']) }}</td>
                        <td class="r font-extrabold">{{ fmt_num($r['closing']) }}</td>
                        <td class="r">{{ fmt_num($r['value_cost']) }}</td>
                        <td class="r">{{ fmt_num($r['value_exfp']) }}</td>
                        <td class="r"><span class="block">{{ $r['sales_tax_rate'] !== null ? fmt_pct($r['sales_tax_rate']) : '–' }}</span><span class="text-[12px] text-muted">{{ fmt_num($r['sales_tax']) }}</span></td>
                        <td class="r">
                            <span class="block">{{ $r['withholding_tax_rate'] !== null ? fmt_pct($r['withholding_tax_rate']) : '–' }}</span><span class="text-[12px] text-muted">{{ fmt_num($r['withholding_tax']) }}</span>
                            @if ($canEditRates)<a href="{{ route('products.index', ['period' => $period->format('Y-m'), 'edit' => $r['id']]) }}#rate-form" class="block text-[12px]">Edit</a>@endif
                        </td>
                        <td class="r">{{ $r['cover'] !== null ? fmt_num($r['cover'], 1).' mo' : '–' }}</td>
                        <td><x-chip :tone="$levels[$r['level']][1]">{{ $levels[$r['level']][0] }}</x-chip></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td>Total</td><td class="r">{{ fmt_num($sum('opening')) }}</td><td class="r">{{ fmt_num($sum('purchases')) }}</td><td class="r">{{ fmt_num($sum('sold')) }}</td><td class="r">{{ fmt_num($sum('bonus')) }}</td>
                    <td class="r">{{ fmt_num($sum('returned')) }}</td><td class="r">{{ fmt_num($sum('closing')) }}</td><td class="r">{{ fmt_num($sum('value_cost')) }}</td>
                    <td class="r">{{ fmt_num($sum('value_exfp')) }}</td><td class="r">{{ fmt_num($sum('sales_tax')) }}</td><td class="r">{{ fmt_num($sum('withholding_tax')) }}</td><td></td><td></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-[12px] text-muted">Sales tax and WHT show each product’s rate, and under it the tax on this month’s confirmed sales in Rs. Rates are set per product under Master data with an effective date. “Lasts” is closing stock divided by average units out per month over the last three months.</p>
    </section>

    @if ($canReceive)
        <section id="receive" class="card scroll-mt-4" aria-labelledby="rcv-h" data-drawer-source>
            <h2 id="rcv-h" class="card-title">Receive stock</h2>
            <form method="post" action="{{ route('inventory.receipts.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                    <div class="flex flex-col gap-1.5">
                        <label for="r-date" class="label">Date received</label>
                        <input id="r-date" name="received_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('received_on', now()->toDateString()) }}" class="field">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="r-ref" class="label">Invoice / GRN no.</label>
                        <input id="r-ref" name="reference" maxlength="100" value="{{ old('reference') }}" class="field">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="r-sup" class="label">Supplier</label>
                        <input id="r-sup" name="supplier" maxlength="150" value="{{ old('supplier') }}" class="field">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="r-note" class="label">Note</label>
                        <input id="r-note" name="note" maxlength="255" value="{{ old('note') }}" class="field">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 rounded-[14px] bg-sunk px-3.5 py-3">
                    <label for="r-file" class="font-bold">Upload a stock file (CSV)</label>
                    <input id="r-file" name="file" type="file" accept=".csv,text/csv" class="text-[13px] file:mr-3 file:rounded-[10px] file:border-0 file:bg-seg file:px-3 file:py-2 file:font-bold file:text-ink">
                    <a href="{{ route('inventory.receipts.template') }}" class="text-[13px]">Download the template</a>
                    <span class="w-full text-[12px] text-muted">Columns: product (code or name), units, unit_cost. Save an Excel sheet as CSV to upload it. If a file is chosen, the rows below are ignored.</span>
                </div>

                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="mb-2 font-bold">Or enter by hand</legend>
                    @for ($i = 0; $i < 5; $i++)
                        <div class="grid gap-2 sm:grid-cols-[minmax(200px,2fr)_120px_140px]">
                            <label for="l{{ $i }}-p" class="sr-only">Product {{ $i + 1 }}</label>
                            <select id="l{{ $i }}-p" name="lines[{{ $i }}][product_id]" class="field">
                                <option value="">Product…</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" @selected(old("lines.$i.product_id") == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <label for="l{{ $i }}-u" class="sr-only">Units {{ $i + 1 }}</label>
                            <input id="l{{ $i }}-u" name="lines[{{ $i }}][units]" type="number" min="1" step="1" placeholder="Units" value="{{ old("lines.$i.units") }}" class="field num">
                            <label for="l{{ $i }}-c" class="sr-only">Unit cost {{ $i + 1 }}</label>
                            <input id="l{{ $i }}-c" name="lines[{{ $i }}][unit_cost]" type="number" min="0" step="0.01" placeholder="Unit cost (optional)" value="{{ old("lines.$i.unit_cost") }}" class="field num">
                        </div>
                    @endfor
                </fieldset>
                <div><button class="btn btn-primary" data-busy-label="Posting…">Post to stock</button></div>
            </form>
        </section>
    @endif

    <section id="receipts" class="card scroll-mt-4" aria-labelledby="rcpt-h">
        <h2 id="rcpt-h" class="card-title">Stock receipts</h2>
        @if ($receipts->isNotEmpty())
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Date</th><th scope="col">Receipt</th><th scope="col" class="r">Products</th><th scope="col" class="r">Units</th><th scope="col">By</th><th scope="col">Status</th>@if ($canReceive)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead>
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
                            @if ($canReceive)
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
