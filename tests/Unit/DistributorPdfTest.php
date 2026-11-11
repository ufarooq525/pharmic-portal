<?php

namespace Tests\Unit;

use App\Modules\Imports\Parsers\PdfImportReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reads the real distributor PDFs and checks the parsed totals against each file's own grand total.
 * The client's files are not kept in the repository: point PHARMIC_SAMPLES at the "Pharmic LH"
 * folder to run these, e.g.  PHARMIC_SAMPLES="C:/Users/.../Pharmic LH" php artisan test
 */
class DistributorPdfTest extends TestCase
{
    public static function files(): array
    {
        return [
            'Hamdan (product grid)' => ['Hamdan ent.Mardan/CUSTOMER WISE (2).PDF', 'matrix', 22, 304, 228818],
            'Monal (product grid)' => ['Monal ent. Batkhela/COSTOMER.PDF', 'matrix', 29, 445, 225310],
            'Saaj (product grid, Dec 2025)' => ['Saaj ent. Swabi/CUSTOMER (4).PDF', 'matrix', 12, 26, 13668],
            'Irfan (line items)' => ['Irfan ent. Temergara/LH COSTUMER.PDF', 'lineitem', 7, 110, 55790],
            'Umar Drug (line items)' => ['Umer ent.Pesshawar/21-CWS.PDF', 'lineitem', 38, 236, 147929],
            'Friends (monthly columns)' => ['Friends ent.Bunir/CUSTANUAL2.pdf', 'monthly', 16, 143, 79454.5],
        ];
    }

    #[DataProvider('files')]
    public function test_parsed_totals_match_the_file(string $file, string $layout, int $customers, float $units, float $value): void
    {
        $dir = getenv('PHARMIC_SAMPLES');
        if (! $dir || ! is_file($path = rtrim($dir, '/\\').'/'.$file)) {
            $this->markTestSkipped('Set PHARMIC_SAMPLES to the folder with the distributor PDFs.');
        }

        $parsed = (new PdfImportReader)->read($path);

        $this->assertSame($layout, $parsed->layout);
        $this->assertCount($customers, $parsed->customers);
        $this->assertTrue($parsed->totalsMatch());
        $this->assertEqualsWithDelta($units, $parsed->parsedUnits(), 0.01);
        $this->assertEqualsWithDelta($value, $parsed->fileValue, 0.5);
    }
}
