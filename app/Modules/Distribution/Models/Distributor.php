<?php

namespace App\Modules\Distribution\Models;

use App\Modules\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distributor extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['opening_balance' => 'float'];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }
}
