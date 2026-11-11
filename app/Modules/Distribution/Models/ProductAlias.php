<?php

namespace App\Modules\Distribution\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How one distributor spells a product in its PDF ("GYNOF OL TAB (N)" -> Gynofol Tab). */
class ProductAlias extends Model
{
    protected $guarded = ['id'];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
