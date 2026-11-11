<?php

namespace App\Modules\Imports\Parsers;

/** "Area : / Customer : / Item :" reports with a bonus column (Irfan, Umar Drug). */
final class LineItemParser implements LayoutParser
{
    public function layout(): string
    {
        return 'lineitem';
    }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/Item\s*:\s*\d+\s*\//', $text) && str_contains($text, 'Sale Report');
    }

    public function parse(string $path, string $text): array
    {
        $n = PdfText::NUM;
        $lines = $customers = [];
        $area = $customer = null;
        $units = $value = null;

        foreach (explode("\n", $text) as $raw) {
            $s = trim($raw);

            if (preg_match("/^Grand Total\s*:\s*($n)\s+($n)\s+($n)\s+($n)/", $s, $m)) {
                $units = PdfText::number($m[1]);
                $value = PdfText::number($m[4]);
            } elseif (preg_match('/^Area\s*:\s*\d+\s*\/\s*(.+?)\s*(Total\s*:.*)?$/', $s, $m) && empty($m[2])) {
                $area = trim($m[1]);
            } elseif (preg_match('/^Customer\s*:\s*(\d+)\s*\/\s*(.+?)\s*(Total\s*:.*)?$/', $s, $m) && empty($m[3])) {
                $customer = ['code' => $m[1], 'name' => trim($m[2]), 'area' => $area];
                $customers[$m[1]] ??= $customer;
            } elseif ($customer && preg_match("/^Item\s*:\s*(\d+)\s*\/\s*(.+?)\s+($n)\s+($n)\s+($n)\s+($n)$/", $s, $m)) {
                $qty = PdfText::number($m[3]);
                $lines[] = [
                    'customer_code' => $customer['code'], 'customer' => $customer['name'], 'area' => $area,
                    'product_code' => $m[1], 'product' => trim($m[2]), 'units' => $qty, 'bonus' => PdfText::number($m[4]),
                    'trade_price' => $qty != 0.0 ? round(PdfText::number($m[5]) / $qty, 2) : null,
                    'value' => PdfText::number($m[6]),
                ];
            }
        }

        return ['lines' => $lines, 'customers' => array_values($customers), 'units' => $units, 'value' => $value];
    }
}
