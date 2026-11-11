<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Distribution\Models\ProductAlias;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ProductRate::class);
    }

    /** How each distributor spells this product in its files. */
    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    public function currentRate(): HasOne
    {
        return $this->hasOne(ProductRate::class)->ofMany(['effective_from' => 'max'], fn ($q) => $q->where('effective_from', '<=', now()));
    }

    /** The rate in force on a given date (sales are valued with the rate of their own month). */
    public function rateOn(CarbonInterface $date): ?ProductRate
    {
        return $this->rates->where('effective_from', '<=', $date)->sortByDesc('effective_from')->first();
    }
}
