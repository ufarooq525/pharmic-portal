<?php

namespace App\Modules\Sales\Repositories;

use App\Modules\Sales\Models\StockMovement;
use App\Modules\Sales\Models\StockReceipt;
use App\Support\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StockReceiptRepository extends Repository
{
    protected function model(): string
    {
        return StockReceipt::class;
    }

    public function latest(int $perPage = 10): LengthAwarePaginator
    {
        return $this->paginate($perPage, fn ($q) => $q->with(['createdBy:id,name', 'revertedBy:id,name'])->latest('received_on')->latest('id'), 'receipts')
            ->fragment('receipts');
    }

    /** product id => units on hand today, for some products, in one grouped query. */
    public function onHand(array $productIds): Collection
    {
        return StockMovement::whereIn('product_id', $productIds)->groupBy('product_id')
            ->selectRaw('product_id, SUM(units) AS on_hand')->pluck('on_hand', 'product_id');
    }
}
