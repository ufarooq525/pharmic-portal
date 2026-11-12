<?php

namespace App\Modules\Access\Repositories;

use App\Models\User;
use App\Support\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends Repository
{
    protected function model(): string
    {
        return User::class;
    }

    public function forAdmin(): Collection
    {
        return $this->query()->with('medicalRep.territory')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function activeByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->where('is_active', true)->first();
    }
}
