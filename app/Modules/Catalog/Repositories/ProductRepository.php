<?php

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Models\Product;
use App\Support\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends Repository
{
    protected function model(): string
    {
        return Product::class;
    }

    public function active(array $columns = ['*']): Collection
    {
        return $this->query()->where('is_active', true)->orderBy('name')->get($columns);
    }

    /** Every product with all its dated rates (newest first) and the spellings linked to it. */
    public function withRatesAndAliases(): Collection
    {
        return $this->query()->orderBy('name')
            ->with(['rates' => fn ($q) => $q->orderByDesc('effective_from'), 'aliases:id,product_id,name_in_file'])->get();
    }

    public function withCurrentRate(): Collection
    {
        return $this->query()->with('currentRate')->orderBy('name')->get();
    }
}
