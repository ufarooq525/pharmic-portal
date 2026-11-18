<?php

namespace App\Modules\Imports\Parsers;

use RuntimeException;

/**
 * SoftTouch "SALES & STOCK REPORT": one row per product with opening stock, purchases, sale,
 * sale return, net sale (each quantity with its bonus), purchase return, transfers and closing stock.
 *
 * It carries no customers, so it is not a layout of its own: it is read next to a customer file whose
 * layout prints no bonus, and gives the bonus per product that is then shared out across customers.
 *
 * A row is: code, name, T.P., tax, then 18 numbers in a fixed order (see COLUMNS).
 */
final class StockReportParser
{
    /** The 18 numbers after the tax, in the order printed. */
    private const COLUMNS = [
        'opening_units', 'opening_value', 'purchase_units', 'purchase_bonus', 'purchase_value', 'bc_in',
        'sale_units', 'sale_bonus', 'return_units', 'return_bonus', 'net_units', 'net_bonus', 'net_value',
        'purchase_return', 'transfer_out', 'transfer_in', 'closing_units', 'closing_value',
    ];

    /** The "Total Units :" line: the quantity columns only. */
    private const TOTAL_COLUMNS = [
        'opening_units', 'purchase_units', 'purchase_bonus', 'bc_in', 'sale_units', 'sale_bonus', 'return_units', 'return_bonus',
        'net_units', 'net_bonus', 'purchase_return', 'transfer_out', 'transfer_in', 'closing_units',
    ];

    /** The figures compared with the report's own grand total. */
    public const CHECKED = ['sale_units', 'sale_bonus', 'return_units', 'return_bonus', 'net_units', 'net_bonus'];

    private const NUM = '/^(?:-?\d[\d,]*(?:\.\d+)?|-?\.\d+)$/';

    public static function matches(string $text): bool
    {
        return (bool) preg_match('/SALES\s*&\s*STOCK\s+REPORT/i', $text) && str_contains($text, 'Net Sale');
    }

    /**
     * @return array{distributor_in_file: string, period_from: ?string, period_to: ?string,
     *     items: list<array<string, mixed>>, printed: ?array<string, float>, unread: list<string>}
     */
    public function read(string $path): array
    {
        $pages = PdfText::pages($path);
        $text = implode("\n", $pages);

        if (! self::matches($text)) {
            throw new RuntimeException('This is not a Sales & Stock report. Upload the distributor’s “SALES & STOCK REPORT” PDF for the same month.');
        }

        $items = $unread = [];
        $printed = null;

        foreach ($pages as $pageIndex => $page) {
            foreach (explode("\n", $page) as $line) {
                if (preg_match('/^\s*Total Units\s*:\s*(.*)$/', $line, $t)) {
                    // Printed for the group, the company and the whole report; the last one is the grand total.
                    $numbers = preg_split('/\s+/', trim($t[1]));
                    if (count($numbers) === count(self::TOTAL_COLUMNS)) {
                        $printed = array_map([PdfText::class, 'number'], array_combine(self::TOTAL_COLUMNS, $numbers));
                    }

                    continue;
                }

                if (! preg_match('/^\s*(\d+)\s+(.+?)\s+(\d+\.\d+)\s+(\S+)\s+(.*\S)\s*$/', $line, $m)) {
                    continue;
                }

                $numbers = preg_split('/\s+/', $m[5]);
                $allNumbers = count(array_filter($numbers, fn ($n) => preg_match(self::NUM, $n))) === count($numbers);

                if (count($numbers) !== count(self::COLUMNS) || ! $allNumbers || ! preg_match(self::NUM, $m[4])) {
                    $unread[] = trim($line);

                    continue;
                }

                $items[] = ['code' => $m[1], 'name' => trim(preg_replace('/\s+/', ' ', $m[2])), 'trade_price' => (float) $m[3]]
                    + array_map([PdfText::class, 'number'], array_combine(self::COLUMNS, $numbers))
                    + ['page' => $pageIndex + 1, 'source' => mb_substr(preg_replace('/\s+/', ' ', trim($line)), 0, 500)];
            }
        }

        [$from, $to] = PdfImportReader::period($text);

        return [
            'distributor_in_file' => PdfImportReader::distributorName($text),
            'period_from' => $from,
            'period_to' => $to,
            'items' => $items,
            'printed' => $printed,
            'unread' => $unread,
        ];
    }

    /** Sum of the rows read, for the figures the report also prints as its grand total. */
    public static function sums(array $items): array
    {
        return array_combine(self::CHECKED, array_map(fn ($k) => (float) array_sum(array_column($items, $k)), self::CHECKED));
    }
}
