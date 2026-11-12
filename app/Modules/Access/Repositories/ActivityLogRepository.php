<?php

namespace App\Modules\Access\Repositories;

use App\Modules\Access\Models\ActivityLog;
use App\Support\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ActivityLogRepository extends Repository
{
    protected function model(): string
    {
        return ActivityLog::class;
    }

    public function latest(?string $action, int $perPage = 50): LengthAwarePaginator
    {
        return $this->paginate($perPage, fn ($q) => $q->with('user:id,name,role')->when($action, fn ($w) => $w->where('action', $action))->latest('id'));
    }

    public function actions(): Collection
    {
        return $this->query()->distinct()->orderBy('action')->pluck('action');
    }
}
