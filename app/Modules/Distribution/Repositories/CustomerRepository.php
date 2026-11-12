<?php

namespace App\Modules\Distribution\Repositories;

use App\Modules\Distribution\Enums\CustomerFilter;
use App\Modules\Distribution\Models\Customer;
use App\Support\Period;
use App\Support\Repositories\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerRepository extends Repository
{
    protected function model(): string
    {
        return Customer::class;
    }

    /** Search and distributor filter shared by the list and the pill counts. */
    public function base(?array $distributorIds, ?int $distributorId, string $search): Builder
    {
        return $this->scopeToDistributors($this->query(), $distributorIds)
            ->when($distributorId, fn (Builder $q) => $q->where('distributor_id', $distributorId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$search}%")->orWhere('area', 'like', "%{$search}%")->orWhere('code', $search)));
    }

    public function filter(Builder $query, CustomerFilter $filter, CarbonImmutable $period): Builder
    {
        return match ($filter) {
            CustomerFilter::Active => $query->whereDate('last_seen', '>=', $period),
            CustomerFilter::New => $query->whereDate('first_seen', $period),
            CustomerFilter::NeedsRep => $query->doesntHave('medicalReps'),
            CustomerFilter::NoPurchase => $query->whereDate('last_seen', '<', $period)->whereDate('last_seen', '>=', $period->subMonths(2)),
            CustomerFilter::Inactive => $query->whereDate('last_seen', '<', $period->subMonths(2)),
            CustomerFilter::All => $query,
        };
    }

    /** @return array<string, int> filter value => count */
    public function counts(Builder $base, CarbonImmutable $period): array
    {
        return collect(CustomerFilter::cases())->mapWithKeys(fn (CustomerFilter $f) => [$f->value => $this->filter(clone $base, $f, $period)->count()])->all();
    }

    /** The paginated list with distributor, reps and this month's units and value, all eager-loaded. */
    public function list(Builder $base, CustomerFilter $filter, CarbonImmutable $period, int $perPage = 30): LengthAwarePaginator
    {
        return $this->filter($base, $filter, $period)
            ->with(['distributor:id,name', 'medicalReps:id,name,territory_id', 'medicalReps.territory:id,name'])
            ->withSum(['saleLines as month_units' => fn ($q) => $q->whereDate('period', $period)], 'units')
            ->withSum(['saleLines as month_value' => fn ($q) => $q->whereDate('period', $period)], 'value_tp')
            ->orderBy('name')->paginate($perPage)->withQueryString();
    }

    /** Customers with confirmed sales in a month, among some distributors. */
    public function boughtIn(CarbonImmutable $period, array $distributorIds): Collection
    {
        $ids = DB::table('sale_lines')->whereBetween('period', [Period::start($period), Period::end($period)])->distinct()->pluck('customer_id');

        return $this->query()->whereIn('id', $ids)->whereIn('distributor_id', $distributorIds)->orderBy('name')->get();
    }
}
