<?php

namespace App\Modules\Sales\Actions;

use App\Models\User;
use App\Modules\Sales\Enums\StockMovementType;
use App\Modules\Sales\Enums\StockReceiptStatus;
use App\Modules\Sales\Models\StockReceipt;
use App\Support\Actions\Action;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Posts a stock delivery: one receipt and a "purchase" movement per product. */
class PostStockReceipt extends Action
{
    /**
     * @param  list<array{product_id: int, units: float, unit_cost: ?float}>  $lines
     * @param  array{received_on: string, reference?: ?string, supplier?: ?string, note?: ?string}  $header
     */
    public function handle(array $header, array $lines, User $user, ?UploadedFile $file = null): StockReceipt
    {
        if ($lines === []) {
            throw new DomainException('Add at least one product with units.');
        }

        $receipt = DB::transaction(function () use ($header, $lines, $user, $file) {
            $receipt = StockReceipt::create($header + [
                'status' => StockReceiptStatus::Posted,
                'created_by' => $user->id,
                'original_name' => $file?->getClientOriginalName(),
                'file_path' => $file?->store('stock-receipts'),
                'line_count' => count($lines),
                'total_units' => array_sum(array_column($lines, 'units')),
            ]);

            foreach ($lines as $line) {
                $receipt->movements()->create([
                    'product_id' => $line['product_id'],
                    'type' => StockMovementType::Purchase,
                    'units' => $line['units'],
                    'unit_cost' => $line['unit_cost'],
                    'moved_on' => $header['received_on'],
                    'note' => 'Stock receipt'.($header['reference'] ?? null ? ' '.$header['reference'] : ''),
                ]);
            }

            return $receipt;
        });

        $this->log('Received stock', 'Receipt #'.$receipt->id.($receipt->reference ? " ({$receipt->reference})" : '').': '.count($lines).' products, '.fmt_num($receipt->total_units).' units'.($receipt->original_name ? " from {$receipt->original_name}" : ''));

        return $receipt;
    }
}
