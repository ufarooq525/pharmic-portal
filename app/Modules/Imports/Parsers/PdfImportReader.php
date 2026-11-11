<?php

namespace App\Modules\Imports\Parsers;

use RuntimeException;

/** Detects a distributor PDF's layout and converts it to a ParsedFile. */
final class PdfImportReader
{
    /** @var list<LayoutParser> */
    private array $parsers;

    public function __construct(?array $parsers = null)
    {
        $this->parsers = $parsers ?? [new MatrixParser, new LineItemParser, new MonthlyParser];
    }

    public function read(string $path): ParsedFile
    {
        $text = PdfText::layout($path);

        foreach ($this->parsers as $parser) {
            if ($parser->matches($text)) {
                $result = $parser->parse($path, $text);
                [$from, $to] = $this->period($text);

                return new ParsedFile($parser->layout(), $this->distributorName($text), $from, $to,
                    $result['lines'], $result['customers'], $result['units'], $result['value'], $result['columns'] ?? []);
            }
        }

        throw new RuntimeException('This PDF layout is not recognised. Ask the distributor for the customer-wise sales report, or add a new layout profile.');
    }

    /** Name used to match a customer across months when the layout has no customer codes. */
    public static function normalise(string $name): string
    {
        $name = str_replace(['MADICAL', 'MIDICAL'], 'MEDICAL', mb_strtoupper($name));

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $name));
    }

    private function distributorName(string $text): string
    {
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, 'Page') && ! str_starts_with($line, 'Syntax')) {
                return preg_split('/\s{2,}/', $line)[0];
            }
        }

        return '';
    }

    /** @return array{?string, ?string} ISO dates */
    private function period(string $text): array
    {
        $date = '(\d{2}[\/-](?:\d{2}|[A-Z]{3})[\/-]\d{2,4})';
        preg_match('/From Date\s*:?\s*'.$date.'/', $text, $from);
        preg_match('/To Date\s*:?\s*'.$date.'/', $text, $to);

        return [isset($from[1]) ? $this->iso($from[1]) : null, isset($to[1]) ? $this->iso($to[1]) : null];
    }

    private function iso(string $date): string
    {
        $months = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];
        [$d, $m, $y] = preg_split('/[\/-]/', $date);
        $m = ctype_alpha($m) ? $months[$m] : (int) $m;
        $y = (int) $y + (strlen($y) === 2 ? 2000 : 0);

        return sprintf('%04d-%02d-%02d', $y, $m, (int) $d);
    }
}
