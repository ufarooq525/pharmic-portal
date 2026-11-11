<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const OPENING = 'opening';

    public const PURCHASE = 'purchase';

    public const SALE = 'sale';

    public const RETURN = 'return';

    public const ADJUSTMENT = 'adjustment';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['moved_on' => 'date', 'units' => 'float', 'unit_cost' => 'float'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
