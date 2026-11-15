<?php

namespace App\Modules\Access\Repositories;

use App\Modules\Access\Models\Role;
use App\Support\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository extends Repository
{
    protected function model(): string
    {
        return Role::class;
    }

    /** Every role with its permissions and how many people have it; the Owner role first. */
    public function forAdmin(): Collection
    {
        return $this->query()->with('permissionRows')->withCount('users')->orderByDesc('is_owner')->orderBy('name')->get();
    }

    public function owner(): Role
    {
        return $this->query()->where('is_owner', true)->firstOrFail();
    }
}
