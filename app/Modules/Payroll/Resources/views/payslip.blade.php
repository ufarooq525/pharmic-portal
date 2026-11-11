@php($rep = $row['rep'])
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip {{ $rep->name }} · {{ $period->format('F Y') }}</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    @vite(['resources/css/app.css'])
    <style>@media print { .no-print { display: none !important; } body { background: #fff; } .card { box-shadow: none; padding: 0; } }</style>
</head>
<body class="mx-auto flex max-w-[800px] flex-col gap-5 p-6">
    <div class="no-print"><button type="button" class="btn btn-primary" onclick="window.print()">Print / save as PDF</button></div>
    <main class="card gap-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <span class="text-[20px] font-extrabold">{{ config('pharmic.company') }}</span>
                <span class="text-muted">Payslip · {{ $period->format('F Y') }}</span>
            </div>
            <div class="flex flex-col items-end gap-1 text-right">
                <span class="text-[18px] font-extrabold">{{ $rep->name }}</span>
                <span class="text-muted">Medical rep · {{ $rep->territory->name }}</span>
            </div>
        </div>
        <table class="data">
            <tbody>
            <tr><td>Base salary</td><td class="r">{{ fmt_num($row['base']) }}</td></tr>
            <tr><td>Incentive on {{ fmt_rs($row['sales']) }} sales (Ex-FP)</td><td class="r">{{ fmt_num($row['incentive']) }}</td></tr>
            @if ($row['allowance'])<tr><td>Allowance</td><td class="r">{{ fmt_num($row['allowance']) }}</td></tr>@endif
            @if ($row['deductions'])<tr><td>Deductions</td><td class="r">−{{ fmt_num($row['deductions']) }}</td></tr>@endif
            </tbody>
            <tfoot><tr><td>Net pay</td><td class="r">{{ fmt_rs($row['net']) }}</td></tr></tfoot>
        </table>
        @if ($row['note'])<p class="m-0 text-muted">{{ $row['note'] }}</p>@endif

        @if ($breakdown)
            <h2 class="card-title">Incentive by customer</h2>
            <table class="data">
                <thead><tr><th scope="col">Customer</th><th scope="col" class="r">Share</th><th scope="col" class="r">Units</th><th scope="col" class="r">Sales Ex-FP</th><th scope="col" class="r">Incentive</th></tr></thead>
                <tbody>
                @foreach ($breakdown as $b)
                    <tr>
                        <td>{{ $b->name }}<span class="block text-[12px] text-muted">{{ $b->area }}</span></td>
                        <td class="r">{{ (int) $b->share_percent }}%</td>
                        <td class="r">{{ fmt_num($b->units) }}</td>
                        <td class="r">{{ fmt_num($b->sales) }}</td>
                        <td class="r">{{ fmt_num($b->incentive) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </main>
</body>
</html>
