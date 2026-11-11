<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'units' => 'float', 'bonus' => 'float', 'rate' => 'float', 'value' => 'float', 'sales_tax_rate' => 'float',
            'sales_tax' => 'float', 'withholding_tax_rate' => 'float', 'withholding_tax' => 'float', 'total' => 'float',
        ];
    }
}
