<?php

namespace App\Modules\Sales\Models;

use App\Modules\Sales\Enums\StockMovementType;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['moved_on' => 'date', 'units' => 'float', 'unit_cost' => 'float', 'type' => StockMovementType::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
