<?php

namespace App\Modules\Access\Models;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of permissions given to staff logins. The Owner role is special: it always has every
 * permission, stores none, and cannot be renamed, edited or deleted.
 */
#[Fillable(['name', 'description'])]
class Role extends Model
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissionRows(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /** @return list<string> permission values, in the order of the Permission enum */
    public function permissionValues(): array
    {
        if ($this->is_owner) {
            return Permission::values();
        }

        return Permission::normalise($this->permissionRows->pluck('permission'));
    }

    protected function casts(): array
    {
        return ['is_owner' => 'boolean'];
    }
}
