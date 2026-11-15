<?php

namespace App\Modules\Distribution\Repositories;

use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\ProductAlias;
use App\Support\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductAliasRepository extends Repository
{
    protected function model(): string
    {
        return ProductAlias::class;
    }

    /** One distributor's spellings, a page at a time (the distributors page shows them under the profile). */
    public function forDistributor(int $distributorId, int $perPage = 50): LengthAwarePaginator
    {
        return $this->paginate($perPage, fn ($q) => $q->where('distributor_id', $distributorId)->with('product:id,name')->orderBy('name_in_file'), 'names_page')->fragment('names');
    }

    /** Name matching tab: every distributor's spellings, optionally one distributor. */
    public function list(?int $distributorId, int $perPage = 50): LengthAwarePaginator
    {
        return $this->paginate($perPage, fn ($q) => $q->with(['distributor:id,name', 'product:id,name'])
            ->when($distributorId, fn ($w) => $w->where('distributor_id', $distributorId))
            ->orderBy(Distributor::select('name')->whereColumn('distributors.id', 'product_aliases.distributor_id'))
            ->orderBy('name_in_file'));
    }
}
