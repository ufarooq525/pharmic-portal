@php
    $t = $totals;
    $statusChip = [$import->status->label(), $import->status->tone()];
    $attention = collect($customers)->where('attention', true)->count();
    $layouts = \App\Modules\Imports\Enums\ReportLayout::options();
    $bonusSource = $import->distributor->bonus_source;
    $failed = collect($import->checks)->reject(fn ($c) => $c['ok']);
    $reupload = route('imports.index', ['period' => $period->format('Y-m'), 'distributor' => $import->distributor_id]).'#upload';
@endphp
<x-layouts.app title="{{ $import->distributor->name }} · {{ $period->format('F Y') }}" description="Review of the customer-wise file from {{ $import->distributor->name }} for {{ $period->format('F Y') }}.">
    <x-page-header :title="$import->distributor->name.' · '.$period->format('F Y')">
        <x-chip :tone="$statusChip[1]" class="text-[13px]">{{ $statusChip[0] }}</x-chip>
        <a href="{{ route('imports.file', $import) }}" class="btn btn-sm">Open the PDF</a>
        <a href="{{ route('imports.index', ['period' => $period->format('Y-m')]) }}" class="btn btn-sm">All files</a>
        @if ($canDiscard && $failed->isEmpty())
            <form method="post" data-ajax action="{{ route('imports.discard', $import) }}" data-confirm="Discard this file? Nothing has been posted from it yet.">
                @csrf
                @method('delete')
                <button class="btn btn-sm">Wrong file? Discard</button>
            </form>
        @endif
    </x-page-header>
    <p class="-mt-3 text-muted">{{ $import->original_name }} · {{ $import->layout?->label() }} · header reads “{{ $import->distributor_in_file }}”</p>

    @if ($failed->isNotEmpty() && ! $import->isConfirmed())
        <section class="flex flex-wrap items-start gap-4 rounded-2xl border-2 border-bad bg-bad-soft p-4 text-bad" role="alert" aria-labelledby="wrong-h">
            <span class="flex size-11 flex-none items-center justify-center rounded-full bg-bad text-[22px] font-extrabold text-surface" aria-hidden="true">!</span>
            <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-1.5">
                <h2 id="wrong-h" class="m-0 text-[18px] font-extrabold">This looks like the wrong file</h2>
                <ul class="m-0 list-disc pl-5 font-semibold">
                    @foreach ($failed as $check)
                        <li>{{ $check['message'] }}</li>
                    @endforeach
                </ul>
                <p class="m-0">Nothing has been posted. Upload the right file, or discard this one.</p>
            </div>
            @if ($canDiscard)
                <div class="flex flex-wrap gap-2 self-center">
                    <a href="{{ $reupload }}" class="btn border-transparent bg-bad text-surface">Upload the right file</a>
                    <form method="post" data-ajax action="{{ route('imports.discard', $import) }}" data-confirm="Discard this file? Nothing has been posted from it.">
                        @csrf
                        @method('delete')
                        <button class="btn border-bad bg-transparent text-bad">Discard</button>
                    </form>
                </div>
            @endif
        </section>
    @elseif ($import->isConfirmed() && $canReverse)
        <p class="m-0 rounded-[14px] bg-sunk px-3.5 py-3 text-[13px] text-muted">Posted by mistake? “Revert import” at the bottom takes its sales back out, returns the units to stock, cancels any invoice issued for the month with a credit note, and removes customers this file added. Keep the file to fix and confirm again, or remove it and upload the right one.</p>
    @endif

    <ul class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4" aria-label="Checks">
        @foreach ($import->checks as $check)
            <li class="flex items-start gap-2.5 rounded-[14px] px-3.5 py-[11px] font-semibold {{ $check['ok'] ? 'bg-ok-soft text-ok' : 'bg-bad-soft text-bad' }}">
                <span aria-hidden="true" class="font-extrabold">{{ $check['ok'] ? '✓' : '✕' }}</span>
                <span><span class="sr-only">{{ $check['ok'] ? 'Passed:' : 'Failed:' }}</span> {{ $check['message'] }}</span>
            </li>
        @endforeach
    </ul>

    @if ($allocated)
        {{-- The customer file prints no bonus: the distributor's Sales & Stock report gives it per product, shared out across customers. --}}
        <section id="stock-report" class="card scroll-mt-4" aria-labelledby="ssr-h">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="ssr-h" class="card-title card-title-icon"><span class="card-icon" style="--tone: var(--sky-c3)"><x-icon name="box" /></span>Sales &amp; Stock report · bonus</h2>
                @if ($import->hasStockReport())
                    <span class="flex flex-wrap items-center gap-2 text-[13px] text-muted">
                        {{ $import->stock_report_name }}
                        <a href="{{ route('imports.stock-report.file', $import) }}" class="btn btn-sm">Open the PDF</a>
                    </span>
                @endif
            </div>
            <p class="m-0 text-[13px] text-muted">This distributor’s customer file prints no bonus. Each product’s net bonus on its Sales &amp; Stock report is shared out across the customers who bought it, in proportion to the scheme. Product totals match the report; each customer’s share is an estimate.</p>

            @if ($reportWarnings)
                <ul class="m-0 grid list-none gap-2 p-0 sm:grid-cols-2" aria-label="Sales & Stock report checks">
                    @foreach ($reportWarnings as $check)
                        <li class="flex items-start gap-2.5 rounded-[14px] px-3.5 py-[11px] font-semibold {{ $check['ok'] ? 'bg-ok-soft text-ok' : 'bg-warn-soft text-warn' }}">
                            <span aria-hidden="true" class="font-extrabold">{{ $check['ok'] ? '✓' : '!' }}</span>
                            <span><span class="sr-only">{{ $check['ok'] ? 'Passed:' : 'Warning:' }}</span> {{ $check['message'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($reportProducts)
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                        <tr>
                            <th scope="col">Product on the report</th><th scope="col" class="r">Units in customer file</th><th scope="col" class="r">Units on report</th>
                            <th scope="col" class="r">Bonus on report</th><th scope="col" class="r">Bonus shared out</th><th scope="col"><span class="sr-only">Check</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($reportProducts as $p)
                            <tr>
                                <td class="font-semibold">{{ $p['name'] }}</td>
                                <td class="r">{{ fmt_num($p['file_units']) }}</td>
                                <td class="r">{{ fmt_num($p['report_units']) }}</td>
                                <td class="r">{{ fmt_num($p['report_bonus']) }}</td>
                                <td class="r">{{ fmt_num($p['given']) }}</td>
                                <td>
                                    @if ($p['units_ok'] && $p['given_ok'])
                                        <x-chip tone="ok">Matches</x-chip>
                                    @else
                                        <x-chip tone="warn">{{ ! $p['units_ok'] ? 'Units differ' : 'Bonus not all given' }}</x-chip>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="r">{{ fmt_num(array_sum(array_column($reportProducts, 'file_units'))) }}</td>
                            <td class="r">{{ fmt_num(array_sum(array_column($reportProducts, 'report_units'))) }}</td>
                            <td class="r">{{ fmt_num(array_sum(array_column($reportProducts, 'report_bonus'))) }}</td>
                            <td class="r">{{ fmt_num(array_sum(array_column($reportProducts, 'given'))) }}</td>
                            <td></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            @if ($canMap)
                <form method="post" data-ajax action="{{ route('imports.stock-report', $import) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex flex-col gap-1.5">
                        <label for="stock-report-file" class="label">{{ $import->hasStockReport() ? 'Replace the Sales & Stock report' : 'Upload the Sales & Stock report for '.$period->format('F Y') }}</label>
                        <input id="stock-report-file" name="stock_report" type="file" accept="application/pdf,.pdf" required class="field w-auto bg-surface">
                    </div>
                    <button class="btn {{ $import->hasStockReport() ? '' : 'btn-primary' }}" data-busy-label="Reading…">Read the report</button>
                </form>
            @endif
        </section>
    @endif

    @if ($unmapped)
        <section class="flex flex-col gap-3 rounded-2xl bg-warn-soft p-4 text-warn" aria-labelledby="map-h">
            <h2 id="map-h" class="m-0 text-[17px] font-extrabold">Link {{ count($unmapped) }} product {{ str('name')->plural(count($unmapped)) }}</h2>
            <p>This distributor spells these products in a way the portal has not seen before. Link each one once; later files are recognised automatically.</p>
            @foreach ($unmapped as $name)
                @if ($canMap)
                    <form method="post" data-ajax action="{{ route('imports.map', $import) }}" class="flex flex-wrap items-end gap-2">
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
                        <button class="btn border-transparent bg-warn text-white">Link</button>
                    </form>
                @else
                    <p class="font-bold">“{{ $name }}”</p>
                @endif
            @endforeach
        </section>
    @endif

    <div class="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-2.5">
        <x-kpi label="Customers" :value="fmt_num(count($customers))" :note="$newCustomers ? $newCustomers.' new to the register' : null" />
        <x-kpi label="Units + bonus" :value="fmt_num($t['units']).' + '.fmt_num($t['bonus'])" />
        <x-kpi label="Value (trade price)" :value="fmt_rs($t['value'])" />
        <x-kpi label="Sales tax" :value="fmt_rs($t['sales_tax'])" />
        <x-kpi label="Withholding tax" :value="fmt_rs($t['withholding_tax'])" />
    </div>

    <section id="customers" class="card scroll-mt-4" aria-labelledby="cust-h">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="cust-h" class="card-title card-title-icon"><span class="card-icon" style="--tone: var(--sky-c1)"><x-icon name="store" /></span>Customer-wise data</h2>
            <nav class="flex flex-wrap gap-1.5" aria-label="Filter customers">
                <a href="{{ request()->fullUrlWithQuery(['show' => null, 'customers_page' => null]) }}" class="pill" @if (! $attentionOnly) aria-current="page" @endif>All · {{ count($customers) }}</a>
                <a href="{{ request()->fullUrlWithQuery(['show' => 'attention', 'customers_page' => null]) }}" class="pill" @if ($attentionOnly) aria-current="page" @endif>Needs attention · {{ $attention }}</a>
            </nav>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Customer</th><th scope="col">Medical rep</th><th scope="col" class="r">Units</th><th scope="col" class="r">Bonus</th>
                    <th scope="col" class="r">Value Rs</th><th scope="col" class="r">Sales tax</th><th scope="col" class="r">WHT</th><th scope="col">Status</th>
                    <th scope="col" class="sticky-end"><span class="sr-only">View</span></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($shown as $c)
                    <tr>
                        <td class="min-w-[200px]"><span class="block font-bold">{{ $c['name'] }}</span><span class="text-[12px] text-muted">{{ $c['code'] ? 'Code '.$c['code'].' · ' : '' }}{{ $c['area'] ?: 'No area' }}</span></td>
                        <td class="text-[13px] {{ $c['reps'] ? '' : 'text-warn' }}">{{ $c['reps'] ?: 'Not assigned' }}</td>
                        <td class="r">{{ fmt_num($c['units']) }}</td>
                        <td class="r">{{ fmt_num($c['bonus']) }}@if ($c['bonus_estimated'])<span class="ml-1 text-[11px] font-bold text-muted" title="Share of the bonus on the Sales & Stock report: an estimate">est.</span>@endif</td>
                        <td class="r">{{ fmt_num($c['value']) }}</td>
                        <td class="r">{{ fmt_num($c['sales_tax']) }}</td>
                        <td class="r">{{ fmt_num($c['withholding_tax']) }}</td>
                        <td><x-chip :tone="$c['tone']">{{ $c['status'] }}</x-chip></td>
                        <td class="sticky-end text-right">
                            {{-- Opens the customer's lines next to the PDF at that customer's page --}}
                            <a href="{{ route('imports.customer', [$import, $c['index']]) }}" data-drawer="#customer-card" data-drawer-title="{{ $c['name'] }}" class="icon-btn" aria-label="View {{ $c['name'] }} with the PDF" title="View with the PDF" data-row-target><x-icon name="eye" /></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty>Every customer in this file is ready.</x-empty></td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr>
                    <td>File total · {{ count($customers) }} {{ str('customer')->plural(count($customers)) }}</td><td></td>
                    <td class="r">{{ fmt_num($totals['units']) }}</td><td class="r">{{ fmt_num($totals['bonus']) }}</td><td class="r">{{ fmt_num($totals['value']) }}</td>
                    <td class="r">{{ fmt_num($totals['sales_tax']) }}</td><td class="r">{{ fmt_num($totals['withholding_tax']) }}</td><td></td><td class="sticky-end"></td>
                </tr>
                </tfoot>
            </table>
        </div>
        {{ $shown->links() }}
        <p class="text-[12px] text-muted">Click a customer to see its product lines next to the PDF. Bonus: {{ lcfirst($bonusSource->label()) }} (<a href="{{ route('distributors.index', ['distributor' => $import->distributor_id]) }}">change</a>). Taxes use each product’s rates for {{ $period->format('F Y') }}.</p>
    </section>

    <div class="action-bar">
        <span>{{ fmt_num($t['units']) }} units + {{ fmt_num($t['bonus']) }} bonus leave stock · {{ fmt_rs($t['value']) }} sales · ST {{ fmt_rs($t['sales_tax']) }} · WHT {{ fmt_rs($t['withholding_tax']) }}</span>
        @if ($canConfirm)
            <form method="post" data-ajax action="{{ route('imports.confirm', $import) }}">
                @csrf
                <button class="btn btn-go">Confirm and post</button>
            </form>
        @elseif ($canReverse)
            <form method="post" data-ajax action="{{ route('imports.reverse', $import) }}" class="flex flex-wrap items-center gap-2"
                  data-confirm="Revert this import? Its sales come out, its {{ fmt_num($t['units'] + $t['bonus']) }} units go back into stock, and any invoice already issued for {{ $period->format('F') }} is cancelled with a credit note.">
                @csrf
                <label for="revert-mode" class="sr-only">After reverting</label>
                <select id="revert-mode" name="remove_file" class="field min-h-10 w-auto border-white/25 bg-transparent py-1.5 text-white [&>option]:text-ink">
                    <option value="0">Keep the file for review</option>
                    <option value="1">Remove the file too</option>
                </select>
                <button class="btn border-transparent bg-bad-soft text-bad" data-busy-label="Reverting…">Revert import</button>
            </form>
        @else
            <span class="font-bold">
                @if ($import->isConfirmed()) Confirmed {{ $import->confirmed_at?->format('j M Y, g:i a') }}
                @elseif ($import->status === \App\Modules\Imports\Enums\ImportStatus::Blocked) Wrong file: upload the right one or discard this
                @elseif ($unmapped) Link every product before confirming
                @else Waiting for Accounts to confirm
                @endif
            </span>
        @endif
    </div>
</x-layouts.app>
