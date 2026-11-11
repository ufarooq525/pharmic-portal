<?php

namespace App\Modules\Imports\Parsers;

/** "Customer Code … Name …" blocks with Jan–Dec columns (Friends). */
final class MonthlyParser implements LayoutParser
{
    public function layout(): string
    {
        return 'monthly';
    }

    public function matches(string $text): bool
    {
        return str_contains($text, 'Customer Code') && (bool) preg_match('/\bJan\b.*\bFeb\b.*\bDec\b/', $text);
    }

    public function parse(string $path, string $text): array
    {
        $n = PdfText::NUM;
        $lines = $customers = [];
        $customer = null;
        $units = $value = null;
        $rows = explode("\n", $text);

        foreach ($rows as $i => $row) {
            if (preg_match('/^\s*Customer Code\s+(\d+)\s+Name\s+(.+?)\s{2,}Address:\s*(.*)$/', $row, $m)) {
                $customer = ['code' => $m[1], 'name' => trim($m[2]), 'area' => trim($m[3])];
                $customers[$m[1]] = $customer;
            } elseif (str_contains($row, 'Total For Company')) {
                preg_match_all("/$n/", $row, $u);
                preg_match_all("/$n/", $rows[$i + 1] ?? '', $v);
                $units = $u[0] ? PdfText::number(end($u[0])) : null;
                $value = $v[0] ? PdfText::number(end($v[0])) : null;
            } elseif ($customer && ! str_starts_with(trim($row), 'Total') && preg_match("/^\s*\d+\s+(.+?)\s{2,}($n)\s+(.*)$/", $row, $m)) {
                preg_match_all("/$n/", $m[3], $nums);

                if (count($nums[0]) >= 3) {
                    $lines[] = [
                        'customer_code' => $customer['code'], 'customer' => $customer['name'], 'area' => $customer['area'],
                        'product' => trim($m[1]), 'units' => PdfText::number($nums[0][count($nums[0]) - 2]), 'bonus' => null,
                        'trade_price' => PdfText::number($m[2]), 'value' => PdfText::number(end($nums[0])),
                    ];
                }
            }
        }

        return ['lines' => $lines, 'customers' => array_values($customers), 'units' => $units, 'value' => $value];
    }
}
