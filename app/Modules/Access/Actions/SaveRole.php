<?php

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\RolePermission;
use App\Modules\Access\Support\AccessGuard;
use App\Support\Actions\Action;

/** Adds a role, or renames it and changes its permissions. Logs exactly what was added and removed. */
class SaveRole extends Action
{
    public function __construct(private readonly AccessGuard $guard) {}

    /** @param list<string> $permissions */
    public function handle(array $attributes, array $permissions, User $actor, ?Role $role = null): Role
    {
        if ($role) {
            $this->guard->assertRoleEditable($role);
        }

        $new = Permission::normalise($permissions);
        $old = $role?->permissionValues() ?? [];
        $added = array_values(array_diff($new, $old));
        $removed = array_values(array_diff($old, $new));
        $this->guard->assertCanGrant($actor, $added);

        return $this->transaction(function () use ($attributes, $role, $new, $added, $removed) {
            $oldName = $role?->name;
            $role ??= new Role;
            $role->fill($attributes)->save();

            RolePermission::where('role_id', $role->id)->delete();
            RolePermission::insert(array_map(fn ($p) => ['role_id' => $role->id, 'permission' => $p], $new));
            $role->unsetRelation('permissionRows');

            if ($oldName === null) {
                $this->log('Added role', "{$role->name}: ".count($new).' permissions');
            } else {
                $this->log('Changed role', implode('; ', array_filter([
                    $role->name,
                    $oldName !== $role->name ? "renamed from {$oldName}" : null,
                    $added ? 'added '.self::labels($added) : null,
                    $removed ? 'removed '.self::labels($removed) : null,
                ])));
            }

            return $role;
        });
    }

    /** @param list<string> $values */
    public static function labels(array $values): string
    {
        return implode(', ', array_map(fn ($v) => Permission::from($v)->label(), $values));
    }
}
