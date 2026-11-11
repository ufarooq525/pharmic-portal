<?php

namespace App\Modules\Imports\Parsers;

/**
 * SoftTouch "CUSTOMER WISE SALES REPORT": one row per customer, one column per product
 * (Hamdan, Monal, Saaj).
 *
 * Plain-text extraction misaligns the product columns, so this parser reads each word's real
 * x-position: a header word starts at the x of its number column. There is no price per line;
 * the grand-total row gives an implied trade price per column, used to verify the column mapping
 * and to value each line.
 */
final class MatrixParser implements LayoutParser
{
    private const NAME_EDGE = 140.0;

    private const SKIP = ['Area Total', 'No of', 'N.O.C', 'Company :', 'Group :', 'Customer Name', 'Page No', 'Powered', 'User :'];

    public function layout(): string
    {
        return 'matrix';
    }

    public function matches(string $text): bool
    {
        return str_contains($text, 'CUSTOMER WISE SALES REPORT') && str_contains($text, 'Customer Name');
    }

    public function parse(string $path, string $text): array
    {
        $num = '/^'.PdfText::NUM.'$/';
        $lines = $customers = $columns = $grandColumns = [];
        $area = $previous = null;
        $units = $value = null;

        foreach (PdfText::wordLines($path) as $page) {
            $texts = array_map(fn ($ws) => trim(implode(' ', array_column($ws, 'text'))), $page);
            $headerAt = null;

            foreach ($texts as $i => $t) {
                if (str_starts_with($t, 'Customer Name')) {
                    $headerAt = $i;
                    break;
                }
            }

            $header = [];

            foreach ($headerAt === null ? [] : array_slice($page, max(0, $headerAt - 3), min(4, $headerAt + 1)) as $ws) {
                if (array_intersect(array_column($ws, 'text'), ['Date', 'Date:', 'Group'])) {
                    continue; // the print-date line sits inside the header band
                }

                foreach ($ws as $w) {
                    if ($w['x0'] > self::NAME_EDGE && $w['text'] !== 'Total') {
                        $header[] = $w;
                    }
                }
            }

            foreach ($page as $i => $ws) {
                if ($headerAt !== null && $i <= $headerAt) {
                    continue;
                }

                $s = $texts[$i];

                if (str_starts_with($s, 'Grand Total')) {
                    $next = $page[$i + 1] ?? [];
                    $q = array_values(array_filter($ws, fn ($w) => preg_match($num, $w['text'])));
                    $v = array_values(array_filter($next, fn ($w) => preg_match($num, $w['text'])));
                    $units = $q ? PdfText::number(end($q)['text']) : null;
                    $value = $v ? PdfText::number(end($v)['text']) : null;

                    foreach (array_slice($q, 0, -1) as $k => $w) {
                        $grandColumns[] = ['x' => round($w['x0']), 'units' => PdfText::number($w['text']), 'value' => isset($v[$k]) && $k < count($v) - 1 ? PdfText::number($v[$k]['text']) : null];
                    }

                    $previous = null;

                    continue;
                }

                if ($s === '' || preg_match('/^\d+$/', $s) || $this->startsWithAny($s, self::SKIP)) {
                    $previous = null;

                    continue;
                }

                $nums = array_values(array_filter($ws, fn ($w) => $w['x0'] > self::NAME_EDGE && preg_match($num, $w['text'])));
                $name = implode(' ', array_column(array_filter($ws, fn ($w) => $w['x1'] < self::NAME_EDGE), 'text'));

                if (! $nums && ! preg_match('/[A-Za-z]/', $name)) {
                    continue; // stray punctuation at page breaks
                }

                if (count($nums) >= 3) {
                    if ($header || ! $columns) {
                        $columns = $this->columns(array_slice($nums, 0, -1), $header);
                        $header = [];
                    }

                    $key = self::key($name, $area);
                    $customers[$key] ??= ['code' => null, 'name' => $name, 'area' => $area];

                    foreach (array_slice($nums, 0, -1) as $w) {
                        $qty = PdfText::number($w['text']);

                        if ($qty != 0.0) {
                            $col = $this->nearest($columns, $w['x0']);
                            $lines[] = ['customer_code' => null, 'customer' => $name, 'area' => $area, 'product_code' => null, 'product' => $col['label'] ?: 'UNLABELLED@'.$col['x'],
                                'units' => $qty, 'bonus' => null, 'trade_price' => null, 'value' => null, '_x' => $col['x'], '_key' => $key];
                        }
                    }

                    $previous = $key;
                } elseif ($previous !== null && $name !== '') {
                    // wrapped customer name: "MARHABA MEDICOSE TAKHT" / "BHAI"
                    $full = $customers[$previous]['name'].' '.$name;
                    $newKey = self::key($full, $area);
                    $customers[$newKey] = ['name' => $full] + $customers[$previous];
                    unset($customers[$previous]);

                    foreach ($lines as &$line) {
                        if ($line['_key'] === $previous) {
                            $line['customer'] = $full;
                            $line['_key'] = $newKey;
                        }
                    }
                    unset($line);

                    $previous = null;
                } elseif ($name !== '') {
                    $area = $name;
                    $previous = null;
                }
            }
        }

        // Price each line from its column's implied trade price (column value / column units).
        $priced = [];

        foreach ($grandColumns as $g) {
            $col = $columns ? $this->nearest($columns, $g['x']) : ['label' => '', 'x' => $g['x']];
            $tp = $g['value'] !== null && $g['units'] != 0.0 ? round($g['value'] / $g['units'], 2) : null;
            $priced[$col['x']] = $tp;
            $grandOut[] = ['label' => $col['label'], 'units' => $g['units'], 'value' => $g['value'], 'implied_tp' => $tp];
        }

        foreach ($lines as &$line) {
            $tp = $priced[$line['_x']] ?? null;
            $line['trade_price'] = $tp;
            $line['value'] = $tp !== null ? round($line['units'] * $tp, 2) : null;
            unset($line['_x'], $line['_key']);
        }
        unset($line);

        return ['lines' => $lines, 'customers' => array_values($customers), 'units' => $units, 'value' => $value, 'columns' => $grandOut ?? []];
    }

    /** Column x-positions come from the first data row; labels from header words that start there. */
    private function columns(array $numberWords, array $header): array
    {
        $xs = array_map(fn ($w) => round($w['x0']), $numberWords);
        $columns = [];

        foreach ($xs as $k => $x) {
            $next = $xs[$k + 1] ?? $x + 29;
            $words = array_values(array_filter($header, fn ($w) => $w['x0'] >= $x - 3 && $w['x0'] < $next - 3));
            usort($words, fn ($a, $b) => [round($a['top']), $a['x0']] <=> [round($b['top']), $b['x0']]);
            $columns[] = ['x' => $x, 'label' => implode(' ', array_column($words, 'text'))];
        }

        return $columns;
    }

    private function nearest(array $columns, float $x): array
    {
        usort($columns, fn ($a, $b) => abs($a['x'] - $x) <=> abs($b['x'] - $x));

        return $columns[0];
    }

    private function startsWithAny(string $s, array $prefixes): bool
    {
        foreach ($prefixes as $p) {
            if (str_starts_with($s, $p)) {
                return true;
            }
        }

        return false;
    }

    private static function key(string $name, ?string $area): string
    {
        return PdfImportReader::normalise($name).'|'.($area ?? '');
    }
}
