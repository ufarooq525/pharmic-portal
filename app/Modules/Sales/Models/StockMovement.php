<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;

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
        return ['moved_on' => 'date', 'units' => 'float'];
    }
}
