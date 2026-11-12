<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Catalog\Models\Product;
use App\Support\Actions\Action;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Turns an uploaded stock CSV into receipt lines; any bad row rejects the whole file. */
class ReadStockFile extends Action
{
    /**
     * Reads a CSV with a header row: product (code or name), units, and optionally unit_cost.
     * Any bad row rejects the whole file, so a half-posted delivery can't happen.
     *
     * @return list<array{product_id: int, units: float, unit_cost: ?float}>
     */
    public function handle(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            throw new DomainException('The file is empty.');
        }

        $header = array_map(fn ($h) => Str::of(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))->lower()->trim()->replace([' ', '-'], '_')->toString(), $header);
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $header, true))->first(fn ($i) => $i !== false);
        $productCol = $col(['product', 'product_code', 'code', 'product_name', 'name']);
        $unitsCol = $col(['units', 'quantity', 'qty']);
        $costCol = $col(['unit_cost', 'cost', 'cost_price']);
        if ($productCol === null || $unitsCol === null) {
            throw new DomainException('The first row must name the columns: product, units and (optional) unit_cost. Download the template to start from.');
        }

        $products = Product::get(['id', 'code', 'name']);
        $find = fn (string $v) => $products->first(fn ($p) => strcasecmp($p->code, $v) === 0 || strcasecmp($p->name, $v) === 0);

        $lines = $errors = [];
        $row = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $row++;
            $name = trim((string) ($cells[$productCol] ?? ''));
            $units = trim((string) ($cells[$unitsCol] ?? ''));
            if ($name === '' && $units === '') {
                continue;
            }
            if ($units === '' || (float) $units == 0.0) {
                continue; // template rows left blank
            }
            $product = $find($name);
            $cost = $costCol !== null ? trim((string) ($cells[$costCol] ?? '')) : '';

            match (true) {
                $product === null => $errors[] = "Row {$row}: no product called “{$name}”.",
                ! is_numeric(str_replace(',', '', $units)) || (float) str_replace(',', '', $units) < 0 => $errors[] = "Row {$row}: units “{$units}” is not a positive number.",
                $cost !== '' && ! is_numeric(str_replace(',', '', $cost)) => $errors[] = "Row {$row}: unit cost “{$cost}” is not a number.",
                default => $lines[] = [
                    'product_id' => $product->id,
                    'units' => (float) str_replace(',', '', $units),
                    'unit_cost' => $cost === '' ? null : (float) str_replace(',', '', $cost),
                ],
            };
        }
        fclose($handle);

        if ($errors) {
            throw new DomainException(implode(' ', array_slice($errors, 0, 8)).(count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : ''));
        }

        return $lines;
    }
}
