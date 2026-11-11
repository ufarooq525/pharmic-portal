@php
    use App\Modules\Ledger\Models\Payment;
    $s = $statement;
    $owed = fn (float $v) => $v > 0 ? 'warn' : ($v < 0 ? 'info' : 'ok');
    $closingLabel = $s && $s['closing'] < 0 ? 'Paid in advance' : 'Balance due';
    $closingNote = $year == now()->format('Y') ? 'Today' : '31 Dec '.$year;
@endphp
<x-layouts.app title="Ledger" description="Distributor ledgers: confirmed monthly sales against payments received, with running balance.">
    <x-page-header title="Ledger" sub="Each distributor’s account: confirmed monthly sales at Ex-FP are debited, payments received are credited. Undoing an import takes its sales out again.">
        @if ($current)
            <a href="{{ route('ledger.export', [$current, 'year' => $year]) }}" class="btn">Export {{ $year }} (CSV)</a>
        @endif
    </x-page-header>

    <div class="flex flex-wrap items-start gap-5">
        <nav aria-label="Distributors" class="flex w-full max-w-[320px] flex-[1_1_250px] flex-col gap-1 rounded-[20px] bg-surface p-2.5 shadow-[var(--sky-shadow)]">
            @foreach ($distributors as $d)
                @php($due = $balances[$d->id] ?? 0)
                <a href="{{ route('ledger.index', ['distributor' => $d->id, 'year' => $year]) }}" @if ($current?->id === $d->id) aria-current="page" @endif
                   class="flex min-h-14 items-center justify-between gap-2.5 rounded-[14px] border-2 px-3 py-2.5 text-ink no-underline {{ $current?->id === $d->id ? 'border-accent bg-soft' : 'border-transparent hover:bg-sunk' }}">
                    <span class="flex min-w-0 flex-col"><b class="font-bold">{{ $d->name }}</b><span class="text-[12px] text-muted">{{ $d->city }}</span></span>
                    @php($dueTone = $owed($due))
                    <x-chip :tone="$dueTone" class="num">{{ $due < 0 ? 'Advance ' : '' }}{{ fmt_num(abs($due)) }}</x-chip>
                </a>
            @endforeach
            <p class="m-0 px-3 py-2 text-[12px] text-muted">Balance due today, Rs</p>
        </nav>

        @if ($current && $s)
            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 class="m-0 text-[22px] font-extrabold tracking-[-0.015em]">{{ $current->name }} · {{ $year }}</h2>
                    <nav class="tabs" aria-label="Year">
                        @foreach (range((int) now()->format('Y') - 2, (int) now()->format('Y')) as $y)
                            <a href="{{ route('ledger.index', ['distributor' => $current->id, 'year' => $y]) }}" class="tab" @if ($y === $year) aria-current="page" @endif>{{ $y }}</a>
                        @endforeach
                    </nav>
                </div>

                <div class="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-2.5">
                    <x-kpi label="Opening balance" :value="fmt_rs($s['opening'])" :note="'1 Jan '.$year" />
                    <x-kpi label="Sales (debit)" :value="fmt_rs($s['debit'])" note="Confirmed, at Ex-FP" />
                    <x-kpi label="Payments (credit)" :value="fmt_rs($s['credit'])" />
                    <x-kpi :label="$closingLabel" :value="fmt_rs(abs($s['closing']))" :note="$closingNote" />
                </div>

                <section class="card" aria-labelledby="stmt-h">
                    <h2 id="stmt-h" class="card-title">Statement</h2>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th scope="col">Date</th><th scope="col">Entry</th><th scope="col" class="r">Debit Rs</th><th scope="col" class="r">Credit Rs</th><th scope="col" class="r">Balance Rs</th>
                                @if ($canRemove)<th scope="col"><span class="sr-only">Actions</span></th>@endif
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="num text-muted">1 Jan</td>
                                <td class="font-semibold">Opening balance</td>
                                <td class="r"></td><td class="r"></td>
                                <td class="r font-bold">{{ fmt_num($s['opening']) }}</td>
                                @if ($canRemove)<td></td>@endif
                            </tr>
                            @foreach ($s['rows'] as $r)
                                <tr>
                                    <td class="num whitespace-nowrap text-muted">{{ $r['date']->format('j M') }}</td>
                                    <td>
                                        @if ($r['kind'] === 'sale')
                                            <a href="{{ route('imports.show', $r['import_id']) }}" class="font-semibold">{{ $r['what'] }}</a>
                                        @else
                                            <span class="font-semibold">{{ $r['what'] }}</span>
                                        @endif
                                        @if ($r['detail'])<span class="block text-[12px] text-muted">{{ $r['detail'] }}</span>@endif
                                    </td>
                                    <td class="r">{{ $r['debit'] ? fmt_num($r['debit']) : '' }}</td>
                                    <td class="r text-ok">{{ $r['credit'] ? fmt_num($r['credit']) : '' }}</td>
                                    <td class="r font-bold">{{ fmt_num($r['balance']) }}</td>
                                    @if ($canRemove)
                                        <td class="text-right">
                                            @if ($r['kind'] === 'payment')
                                                <form method="post" action="{{ route('ledger.payments.destroy', $r['payment_id']) }}" data-confirm="Remove this payment of {{ fmt_rs($r['credit']) }} from the ledger?">
                                                    @csrf
                                                    @method('delete')
                                                    <button class="btn btn-sm">Remove</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr>
                                <td></td><td>{{ $closingLabel }}</td>
                                <td class="r">{{ fmt_num($s['debit']) }}</td><td class="r">{{ fmt_num($s['credit']) }}</td><td class="r">{{ fmt_num($s['closing']) }}</td>
                                @if ($canRemove)<td></td>@endif
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    @if (! $s['rows'])
                        <x-empty>No confirmed sales or payments for {{ $current->name }} in {{ $year }} yet.</x-empty>
                    @endif
                </section>

                @if ($canRecord)
                    <section class="card" aria-labelledby="pay-h">
                        <h2 id="pay-h" class="card-title">Record a payment from {{ $current->name }}</h2>
                        <form method="post" action="{{ route('ledger.payments.store', $current) }}" class="grid gap-4 sm:grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                            @csrf
                            <div class="flex flex-col gap-1.5">
                                <label for="p-date" class="label">Date received</label>
                                <input id="p-date" name="paid_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}" class="field">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="p-amount" class="label">Amount, Rs</label>
                                <input id="p-amount" name="amount" type="number" required min="1" step="0.01" inputmode="decimal" value="{{ old('amount') }}" class="field num">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="p-method" class="label">How</label>
                                <select id="p-method" name="method" class="field">
                                    @foreach (Payment::METHODS as $key => $label)
                                        <option value="{{ $key }}" @selected(old('method', 'bank') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="p-ref" class="label">Cheque / transaction no.</label>
                                <input id="p-ref" name="reference" maxlength="100" value="{{ old('reference') }}" class="field">
                            </div>
                            <div class="flex flex-col gap-1.5 sm:col-span-full">
                                <label for="p-note" class="label">Note</label>
                                <input id="p-note" name="note" maxlength="255" value="{{ old('note') }}" class="field">
                            </div>
                            <div class="sm:col-span-full"><button class="btn btn-primary">Record payment</button></div>
                        </form>
                    </section>
                @endif

                <p class="m-0 text-[13px] text-muted">Sales are posted at Ex-FP value, before sales tax.</p>
            </div>
        @endif
    </div>
</x-layouts.app>
