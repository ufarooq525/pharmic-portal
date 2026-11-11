<?php

namespace App\Modules\Imports\Parsers;

/**
 * Normalised result of reading one distributor file, whatever its layout.
 *
 * Each line: customer_code, customer, area, product_code (the distributor's own item code, if printed), product, units, bonus, trade_price, value.
 * Each customer: code, name, area (includes customers listed with zero units).
 */
final class ParsedFile
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array<string, mixed>>  $customers
     * @param  list<array<string, mixed>>  $columns  matrix layout only: label, units, value, implied_tp
     */
    public function __construct(
        public readonly string $layout,
        public readonly string $distributorInFile,
        public readonly ?string $periodFrom,
        public readonly ?string $periodTo,
        public readonly array $lines,
        public readonly array $customers,
        public readonly ?float $fileUnits,
        public readonly ?float $fileValue,
        public readonly array $columns = [],
    ) {}

    public function parsedUnits(): float
    {
        return (float) array_sum(array_column($this->lines, 'units'));
    }

    public function parsedValue(): ?float
    {
        $values = array_filter(array_column($this->lines, 'value'), fn ($v) => $v !== null);

        return $values === [] ? null : (float) array_sum($values);
    }

    /** Parsed totals must equal the grand total printed in the file. */
    public function totalsMatch(): bool
    {
        if ($this->fileUnits === null || abs($this->fileUnits - $this->parsedUnits()) > 0.01) {
            return false;
        }

        $value = $this->parsedValue();

        return $value === null || $this->fileValue === null || abs($this->fileValue - $value) < 1;
    }
}
