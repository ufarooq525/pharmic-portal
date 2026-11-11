{{-- Invoice lines table, shared by the screen and the print page. --}}
<div class="table-wrap">
    <table class="data">
        <thead>
        <tr>
            <th scope="col">Product</th><th scope="col" class="r">Units</th><th scope="col" class="r">Bonus</th><th scope="col" class="r">Rate Rs</th><th scope="col" class="r">Value Rs</th>
            <th scope="col" class="r">ST %</th><th scope="col" class="r">Sales tax</th><th scope="col" class="r">WHT %</th><th scope="col" class="r">WHT</th><th scope="col" class="r">Total Rs</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($lines as $l)
            <tr>
                <td class="font-semibold">{{ $l['product_name'] }}</td>
                <td class="r">{{ fmt_num($l['units']) }}</td>
                <td class="r">{{ fmt_num($l['bonus']) }}</td>
                <td class="r">{{ fmt_num($l['rate']) }}</td>
                <td class="r">{{ fmt_num($l['value']) }}</td>
                <td class="r">{{ fmt_pct($l['sales_tax_rate']) }}</td>
                <td class="r">{{ fmt_num($l['sales_tax']) }}</td>
                <td class="r">{{ fmt_pct($l['withholding_tax_rate']) }}</td>
                <td class="r">{{ fmt_num($l['withholding_tax']) }}</td>
                <td class="r font-bold">{{ fmt_num($l['total']) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td>Total</td><td class="r">{{ fmt_num($totals['units']) }}</td><td class="r">{{ fmt_num($totals['bonus']) }}</td><td></td><td class="r">{{ fmt_num($totals['value']) }}</td>
            <td></td><td class="r">{{ fmt_num($totals['sales_tax']) }}</td><td></td><td class="r">{{ fmt_num($totals['withholding_tax']) }}</td><td class="r">{{ fmt_num($totals['total']) }}</td>
        </tr>
        </tfoot>
    </table>
</div>
