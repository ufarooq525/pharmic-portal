<?php

namespace App\Modules\Imports\Repositories;

use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Models\Import;
use App\Support\Repositories\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ImportRepository extends Repository
{
    protected function model(): string
    {
        return Import::class;
    }

    /** The latest file per distributor for a month, keyed by distributor id. */
    public function latestPerDistributor(CarbonImmutable $period): Collection
    {
        return $this->query()->whereDate('period', $period)->latest('id')->get()->unique('distributor_id')->keyBy('distributor_id');
    }

    public function latestFor(int $distributorId): ?Import
    {
        return $this->query()->where('distributor_id', $distributorId)->latest('period')
            ->first(['id', 'period', 'status', 'layout', 'distributor_in_file', 'original_name']);
    }

    /** Distributors whose file for the month is not confirmed yet (the badge in the sidebar). */
    public function unconfirmedCount(CarbonImmutable $period, int $distributors): int
    {
        return max(0, $distributors - $this->query()->whereDate('period', $period)->where('status', ImportStatus::Confirmed)->distinct()->count('distributor_id'));
    }
}
