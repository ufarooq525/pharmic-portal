<?php

namespace App\Support\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic read and write access for one model. Module repositories extend it and add the
 * queries their screens need, so controllers and actions never build queries themselves.
 *
 * @template TModel of Model
 */
abstract class Repository
{
    /** @return class-string<TModel> */
    abstract protected function model(): string;

    /** @return Builder<TModel> */
    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /** @return TModel|null */
    public function find(int $id, array $with = []): ?Model
    {
        return $this->query()->with($with)->find($id);
    }

    /** @return TModel */
    public function findOrFail(int $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    /** @return Collection<int, TModel> */
    public function all(array $columns = ['*'], array $with = [], ?string $orderBy = null): Collection
    {
        return $this->query()->with($with)->when($orderBy, fn ($q) => $q->orderBy($orderBy))->get($columns);
    }

    /** Paginated list; $filter adds where/with/order clauses for the screen. */
    public function paginate(int $perPage = 25, ?callable $filter = null, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = $this->query();
        if ($filter) {
            $filter($query);
        }

        return $query->paginate($perPage, ['*'], $pageName)->withQueryString();
    }

    /** @return TModel */
    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /** @param TModel $model */
    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    /** Limits a query to the distributors a user may see; null means all. */
    protected function scopeToDistributors($query, ?array $distributorIds, string $column = 'distributor_id')
    {
        return $query->when($distributorIds !== null, fn ($q) => $q->whereIn($column, $distributorIds));
    }
}
