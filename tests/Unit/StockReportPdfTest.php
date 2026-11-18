<?php

namespace Tests\Unit;

use App\Modules\Imports\Parsers\StockReportParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reads the real Sales & Stock reports and checks the rows against each report's own grand total.
 * Like DistributorPdfTest, it needs PHARMIC_SAMPLES pointing at the "Pharmic LH" folder.
 */
class StockReportPdfTest extends TestCase
{
    public static function files(): array
    {
        return [
            'Hamdan, Sep 2026' => ['Hamdan ent.Mardan/CLOSING SSR (1).PDF', 'HAMDAN ENTERPRISES', '2026-09-01', 17, 327, 102],
            'Hamdan, Oct 2026' => ['Hamdan ent.Mardan/CLOSING SSR (2).PDF', 'HAMDAN ENTERPRISES', '2026-10-01', 17, 304, 80],
            'Monal, Jul 2026' => ['Monal ent. Batkhela/PHARMIC LH CLOSING SSR.PDF', 'MONAL ENTERPRISES', '2026-07-01', 17, 231, 63],
            'Umar Drug, Aug 2026' => ['Umer ent.Pesshawar/umar aug stats.PDF', 'UMAR DRUG AGENCIES PESHAWAR', '2026-08-01', 16, 566, 167],
        ];
    }

    #[DataProvider('files')]
    public function test_rows_add_up_to_the_printed_grand_total(string $file, string $header, string $from, int $products, float $netUnits, float $netBonus): void
    {
        $dir = getenv('PHARMIC_SAMPLES');
        if (! $dir || ! is_file($path = rtrim($dir, '/\\').'/'.$file)) {
            $this->markTestSkipped('Set PHARMIC_SAMPLES to the folder with the distributor PDFs.');
        }

        $report = (new StockReportParser)->read($path);
        $sums = StockReportParser::sums($report['items']);

        $this->assertSame($header, $report['distributor_in_file']);
        $this->assertSame($from, $report['period_from']);
        $this->assertCount($products, $report['items']);
        $this->assertSame([], $report['unread']);
        $this->assertEqualsWithDelta($netUnits, $sums['net_units'], 0.01);
        $this->assertEqualsWithDelta($netBonus, $sums['net_bonus'], 0.01);
        foreach (StockReportParser::CHECKED as $key) {
            $this->assertEqualsWithDelta($report['printed'][$key], $sums[$key], 0.01, $key);
        }
    }
}
