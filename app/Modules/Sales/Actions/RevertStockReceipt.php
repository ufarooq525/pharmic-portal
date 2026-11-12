<?php

namespace App\Modules\Sales\Actions;

use App\Models\User;
use App\Modules\Sales\Enums\StockReceiptStatus;
use App\Modules\Sales\Models\StockReceipt;
use App\Modules\Sales\Repositories\StockReceiptRepository;
use App\Support\Actions\Action;
use DomainException;
use Illuminate\Support\Facades\DB;

class RevertStockReceipt extends Action
{
    public function __construct(private readonly StockReceiptRepository $receipts) {}

    /** Takes the receipt's units back out. Refused if that would leave a product below zero today. */
    public function handle(StockReceipt $receipt, User $user): void
    {
        if (! $receipt->isPosted()) {
            throw new DomainException('This receipt is already reverted.');
        }

        DB::transaction(function () use ($receipt, $user) {
            $short = [];
            $movements = $receipt->movements()->with('product:id,name')->get();
            // Stock on hand for every product in the receipt, in one grouped query.
            $stock = $this->receipts->onHand($movements->pluck('product_id')->all());
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
            $receipt->update(['status' => StockReceiptStatus::Reverted, 'reverted_by' => $user->id, 'reverted_at' => now()]);
        });

        $this->log('Reverted stock receipt', 'Receipt #'.$receipt->id.($receipt->reference ? " ({$receipt->reference})" : '').': '.fmt_num($receipt->total_units).' units taken back out');
    }
}
