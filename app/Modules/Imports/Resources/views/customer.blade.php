{{-- One customer of an import, for the review drawer (#customer-card): what was read, next to the PDF at that customer's page. --}}
@php
    $c = $customer;
    $pdf = route('imports.view', $import).'#page='.($page ?? 1).'&zoom=page-width';
@endphp
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $c['name'] }} · {{ $import->distributor->name }}</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    @include('partials.view-transition-guard')
    @vite(['resources/css/app.css'])
</head>
<body class="mx-auto flex max-w-[1000px] flex-col gap-5 p-6">
<section id="customer-card" class="card flex flex-col gap-4">
    <h2 class="card-title">{{ $c['name'] }}</h2>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] text-muted">
        <x-chip :tone="$c['tone']">{{ $c['status'] }}</x-chip>
        <span>{{ $c['code'] ? 'Code '.$c['code'] : 'No customer code in the file' }}</span>
        <span>{{ $c['area'] ?: 'No area' }}</span>
        <span class="{{ $c['reps'] ? '' : 'font-semibold text-warn' }}">Medical rep: {{ $c['reps'] ?: 'not assigned' }}</span>
    </div>

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-5">
        @foreach ([['Units', $c['units']], ['Bonus', $c['bonus']], ['Value, Rs', $c['value']], ['Sales tax', $c['sales_tax']], ['WHT', $c['withholding_tax']]] as [$label, $value])
            <div class="flex flex-col gap-0.5 rounded-2xl bg-sunk px-3.5 py-2.5">
                <span class="text-[12px] font-bold text-muted">{{ $label }}</span>
                <span class="num text-[17px] font-semibold">{{ fmt_num($value) }}</span>
            </div>
        @endforeach
    </div>

    @if ($c['lines'])
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">In the file</th><th scope="col">Product</th><th scope="col" class="r">Units</th><th scope="col" class="r">Bonus</th>
                    <th scope="col" class="r">Value Rs</th><th scope="col" class="r">Sales tax</th><th scope="col" class="r">WHT</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($c['lines'] as $l)
                    <tr @if (! $l['product']) class="bg-bad-soft" @endif>
                        <td><span class="font-mono text-[12.5px]">{{ $l['code_in_file'] ? $l['code_in_file'].' · ' : '' }}{{ $l['name_in_file'] }}</span>@if ($l['note'])<span class="block text-[12px] {{ $l['product'] ? 'text-muted' : 'font-semibold text-bad' }}">{{ $l['note'] }}</span>@endif</td>
                        <td class="font-semibold">{{ $l['product'] ?? 'Not linked' }}</td>
                        <td class="r">{{ fmt_num($l['units']) }}</td>
                        <td class="r">{{ fmt_num($l['bonus']) }}</td>
                        <td class="r">{{ fmt_num($l['value']) }}</td>
                        <td class="r">{{ $l['sales_tax_rate'] !== null ? fmt_pct($l['sales_tax_rate']).' · '.fmt_num($l['sales_tax']) : '–' }}</td>
                        <td class="r">{{ $l['withholding_tax_rate'] !== null ? fmt_pct($l['withholding_tax_rate']).' · '.fmt_num($l['withholding_tax']) : '–' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty>Listed in the file with no product lines.</x-empty>
    @endif

    {{-- The PDF itself, at this customer's page, to check the figures against --}}
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="m-0 text-[15px] font-extrabold">In the PDF</h3>
            @if ($hasFile)
            <span class="flex flex-wrap items-center gap-2 text-[13px] text-muted">
                {{ $page ? 'Page '.$page.' of '.$import->original_name : 'Couldn’t find this customer in the file’s text; showing page 1. Use Ctrl+F in the PDF.' }}
                <a href="{{ route('imports.view', $import) }}#page={{ $page ?? 1 }}" target="_blank" rel="noopener" class="btn btn-sm"><x-icon name="file" :size="16" /> Open in a new tab</a>
            </span>
            @endif
        </div>
        @if ($hasFile)
        <iframe src="{{ $pdf }}" title="{{ $import->original_name }}, page {{ $page ?? 1 }}" class="h-[70vh] min-h-[420px] w-full rounded-2xl border border-line bg-sunk"></iframe>
        @else
            <x-empty>The PDF for this import is no longer stored, so it can’t be shown here.</x-empty>
        @endif
    </div>
</section>
</body>
</html>
