<?php

namespace App\Modules\Distribution\Repositories;

use App\Modules\Distribution\Models\Distributor;
use App\Support\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;

class DistributorRepository extends Repository
{
    protected function model(): string
    {
        return Distributor::class;
    }

    /** Distributors a user may see, by name. @param list<int>|null $ids */
    public function visible(?array $ids, array $columns = ['*']): Collection
    {
        return $this->scopeToDistributors($this->query(), $ids, 'id')->orderBy('name')->get($columns);
    }

    /** With counts for the Distributors & formats tab. */
    public function withCounts(?array $ids): Collection
    {
        return $this->scopeToDistributors($this->query(), $ids, 'id')->withCount(['aliases', 'customers', 'imports'])->orderBy('name')->get();
    }
}
