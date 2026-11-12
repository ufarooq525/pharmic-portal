@php
    $r = collect($rows);
    $q = ['period' => $period->format('Y-m')];
@endphp
<x-layouts.app title="MR payroll" description="Base salary plus the percentage incentive each medical rep earned from confirmed sales.">
    <x-page-header title="MR payroll" sub="Base salary plus the percentage incentive each medical rep earned from confirmed {{ $period->format('F') }} sales. A shared customer’s incentive is split by its share." />

    <div class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3.5">
        <x-kpi label="Base salaries" :value="fmt_rs($r->sum('base'))" :note="$r->count().' medical reps'" />
        <x-kpi label="Incentive" :value="fmt_rs($r->sum('incentive'))" :note="'from '.$period->format('F').' sales'" />
        <x-kpi label="Net payroll" :value="fmt_rs($r->sum('net'))" note="to pay" />
    </div>

    <section class="card" aria-labelledby="pay-h">
        <h2 id="pay-h" class="card-title">{{ $period->format('F Y') }} payroll</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Medical rep</th><th scope="col">Territory</th><th scope="col" class="r">Sales Ex-FP</th><th scope="col" class="r">Base salary</th><th scope="col" class="r">Incentive</th>
                    <th scope="col" class="r">Allowance</th><th scope="col" class="r">Deductions</th><th scope="col" class="r">Net pay</th><th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    @php($rep = $row['rep'])
                    <tr>
                        <td class="font-bold">{{ $rep->name }}@if ($row['note'])<span class="block text-[12px] font-normal text-muted">{{ $row['note'] }}</span>@endif</td>
                        <td>{{ $rep->territory->name }}</td>
                        <td class="r">{{ fmt_num($row['sales']) }}</td>
                        <td class="r">{{ fmt_num($row['base']) }}</td>
                        <td class="r">{{ fmt_num($row['incentive']) }}</td>
                        <td class="r">{{ fmt_num($row['allowance']) }}</td>
                        <td class="r">{{ fmt_num($row['deductions']) }}</td>
                        <td class="r font-extrabold">{{ fmt_num($row['net']) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('payroll.payslip', [$rep] + $q) }}" target="_blank" rel="noopener" class="btn btn-sm">Payslip</a>
                            <a href="{{ whatsapp_url($rep->phone, config('pharmic.company')." payslip, {$period->format('F Y')}\n{$rep->name} · {$rep->territory->name}\nBase ".fmt_rs($row['base']).' + incentive '.fmt_rs($row['incentive']).($row['allowance'] ? ' + allowance '.fmt_rs($row['allowance']) : '').($row['deductions'] ? ' − deductions '.fmt_rs($row['deductions']) : '')."\nNet pay: ".fmt_rs($row['net'])) }}" target="_blank" rel="noopener" class="btn btn-sm">WhatsApp</a>
                            @if ($canEdit)<a href="{{ route('payroll.index', $q + ['edit' => $rep->id]) }}#adjust" data-drawer="#adjust" class="btn btn-sm">Adjust</a>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr><td>Total</td><td></td><td class="r">{{ fmt_num($r->sum('sales')) }}</td><td class="r">{{ fmt_num($r->sum('base')) }}</td><td class="r">{{ fmt_num($r->sum('incentive')) }}</td><td class="r">{{ fmt_num($r->sum('allowance')) }}</td><td class="r">{{ fmt_num($r->sum('deductions')) }}</td><td class="r">{{ fmt_num($r->sum('net')) }}</td><td></td></tr>
                </tfoot>
            </table>
        </div>
        <p class="m-0 text-[12px] text-muted">Incentive is each product’s incentive % on the Ex-FP value of confirmed sales to the rep’s customers. Base salaries and phone numbers are set under Master data → Medical reps.</p>
    </section>

    @if ($canEdit && ($row = $r->first(fn ($x) => $x['rep']->id === $editing)))
        <section id="adjust" class="card scroll-mt-4" aria-labelledby="adj-h" data-drawer-source>
            <h2 id="adj-h" class="card-title">Adjust {{ $row['rep']->name }}’s {{ $period->format('F') }} pay</h2>
            <form method="post" action="{{ route('payroll.adjust', $row['rep']) }}" class="grid gap-4 sm:grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                @csrf
                @method('put')
                <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                <div class="flex flex-col gap-1.5">
                    <label for="adj-a" class="label">Allowance, Rs</label>
                    <input id="adj-a" name="allowance" type="number" min="0" step="1" value="{{ old('allowance', $row['allowance']) }}" class="field num">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="adj-d" class="label">Deductions, Rs</label>
                    <input id="adj-d" name="deductions" type="number" min="0" step="1" value="{{ old('deductions', $row['deductions']) }}" class="field num">
                </div>
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label for="adj-n" class="label">Note (shows on the payslip)</label>
                    <input id="adj-n" name="note" maxlength="255" value="{{ old('note', $row['note']) }}" class="field" placeholder="e.g. Advance recovered">
                </div>
                <div class="flex gap-2 sm:col-span-full">
                    <button class="btn btn-primary">Save</button>
                    <button type="button" class="btn" data-drawer-close>Cancel</button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.app>
