<?php

namespace App\Modules\Access\Repositories;

use App\Models\User;
use App\Support\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserRepository extends Repository
{
    protected function model(): string
    {
        return User::class;
    }

    private const WITH = ['medicalRep.territory', 'role.permissionRows', 'extraPermissionRows'];

    /** People for the admin list, active first, with everything the table shows eager-loaded. Search matches name, email or role. */
    public function forAdmin(int $perPage = 25, string $search = ''): LengthAwarePaginator
    {
        return $this->paginate($perPage, fn ($q) => $q->with(self::WITH)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhereHas('role', fn ($r) => $r->where('name', 'like', $like)));
            })
            ->orderByDesc('is_active')->orderBy('name'));
    }

    /** One person for the edit form (they may be on another page of the list). */
    public function forEdit(?int $id): ?User
    {
        return $id ? $this->find($id, self::WITH) : null;
    }

    /** @return Collection<int, string> names of the people who have a role */
    public function namesWithRole(int $roleId): Collection
    {
        return $this->query()->where('role_id', $roleId)->orderBy('name')->pluck('name');
    }

    public function activeByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->where('is_active', true)->first();
    }
}
