<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\StockMovement;
use App\Modules\Sales\Models\StockReceipt;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Posts stock deliveries and reverts them. Every unit in stock traces back to a movement. */
class StockReceiptService
{
    /**
     * @param  list<array{product_id: int, units: float, unit_cost: ?float}>  $lines
     * @param  array{received_on: string, reference?: ?string, supplier?: ?string, note?: ?string}  $header
     */
    public function post(array $header, array $lines, User $user, ?UploadedFile $file = null): StockReceipt
    {
        if ($lines === []) {
            throw new DomainException('Add at least one product with units.');
        }

        return DB::transaction(function () use ($header, $lines, $user, $file) {
            $receipt = StockReceipt::create($header + [
                'status' => StockReceipt::POSTED,
                'created_by' => $user->id,
                'original_name' => $file?->getClientOriginalName(),
                'file_path' => $file?->store('stock-receipts'),
                'line_count' => count($lines),
                'total_units' => array_sum(array_column($lines, 'units')),
            ]);

            foreach ($lines as $line) {
                $receipt->movements()->create([
                    'product_id' => $line['product_id'],
                    'type' => StockMovement::PURCHASE,
                    'units' => $line['units'],
                    'unit_cost' => $line['unit_cost'],
                    'moved_on' => $header['received_on'],
                    'note' => 'Stock receipt'.($header['reference'] ?? null ? ' '.$header['reference'] : ''),
                ]);
            }

            return $receipt;
        });
    }

    /** Takes the receipt's units back out. Refused if that would leave a product below zero today. */
    public function revert(StockReceipt $receipt, User $user): void
    {
        if (! $receipt->isPosted()) {
            throw new DomainException('This receipt is already reverted.');
        }

        DB::transaction(function () use ($receipt, $user) {
            $short = [];
            $movements = $receipt->movements()->with('product:id,name')->get();
            // Stock on hand for every product in the receipt, in one grouped query.
            $stock = StockMovement::whereIn('product_id', $movements->pluck('product_id'))->groupBy('product_id')
                ->selectRaw('product_id, SUM(units) AS on_hand')->pluck('on_hand', 'product_id');
            foreach ($movements as $m) {
                $onHand = (float) ($stock[$m->product_id] ?? 0);
                if ($onHand - $m->units < 0) {
                    $short[] = "{$m->product->name} (".fmt_num($onHand).' on hand, '.fmt_num($m->units).' to take out)';
                }
            }
            if ($short) {
                throw new DomainException('Reverting would leave negative stock: '.implode('; ', $short).'. Some of this stock has already been sold.');
            }

            $receipt->movements()->delete();
            $receipt->update(['status' => StockReceipt::REVERTED, 'reverted_by' => $user->id, 'reverted_at' => now()]);
        });
    }

    /**
     * Reads a CSV with a header row: product (code or name), units, and optionally unit_cost.
     * Any bad row rejects the whole file, so a half-posted delivery can't happen.
     *
     * @return list<array{product_id: int, units: float, unit_cost: ?float}>
     */
    public function readCsv(UploadedFile $file): array
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
