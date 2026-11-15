<?php

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Modules\Access\Enums\LoginType;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\UserPermission;
use App\Modules\Access\Repositories\UserRepository;
use App\Modules\Access\Support\AccessGuard;
use App\Support\Actions\Action;

/**
 * Adds a person, or changes their login type, role, extra permissions, data limits and status.
 * Nobody can give more than they have, and there is always at least one active owner.
 */
class SaveUser extends Action
{
    public function __construct(private readonly UserRepository $users, private readonly AccessGuard $guard) {}

    /**
     * @param  array  $attributes  name, email, password, is_active, login_type, role_id, medical_rep_id, distributor_ids
     * @param  list<string>  $extras  permissions on top of the role
     */
    public function handle(array $attributes, array $extras, User $actor, ?User $user = null): User
    {
        $type = $attributes['login_type'] instanceof LoginType ? $attributes['login_type'] : LoginType::from($attributes['login_type']);
        $role = $type === LoginType::Staff ? Role::with('permissionRows')->findOrFail($attributes['role_id']) : null;

        // What only makes sense for some people is cleared for the others.
        $attributes['login_type'] = $type;
        $attributes['role_id'] = $role?->id;
        $attributes['medical_rep_id'] = $type === LoginType::MedicalRep ? $attributes['medical_rep_id'] : null;
        if ($role === null || $role->is_owner || empty($attributes['distributor_ids'])) {
            $attributes['distributor_ids'] = null;
        }
        $roleValues = $role?->permissionValues() ?? [];
        $extras = ($role === null || $role->is_owner) ? [] : array_values(array_diff(Permission::normalise([...$roleValues, ...$extras]), $roleValues));

        $this->guard->assertCanManagePerson($actor, $user, $role);
        if ($role && $user?->role_id !== $role->id) {
            $this->guard->assertCanGrant($actor, $roleValues);
        }
        $oldExtras = $user?->extraPermissionValues() ?? [];
        $this->guard->assertCanGrant($actor, array_values(array_diff($extras, $oldExtras)));
        if ($user) {
            $this->guard->assertOwnerRemains($user, ($attributes['is_active'] ?? true) && (bool) $role?->is_owner);
        }

        return $this->transaction(function () use ($attributes, $extras, $oldExtras, $user, $role) {
            $before = $user ? [$user->roleLabel(), $user->is_active] : null;

            if ($user === null) {
                $user = $this->users->create($attributes + ['is_active' => true]);
            } else {
                $this->users->update($user, $attributes);
            }

            UserPermission::where('user_id', $user->id)->delete();
            UserPermission::insert(array_map(fn ($p) => ['user_id' => $user->id, 'permission' => $p], $extras));
            $user->forgetPermissions();

            $added = array_values(array_diff($extras, $oldExtras));
            $removed = array_values(array_diff($oldExtras, $extras));
            $parts = array_filter([
                $before === null ? "{$user->name} ({$user->email}) as {$user->roleLabel()}" : $user->name,
                $before && $before[0] !== $user->roleLabel() ? "role {$before[0]} → {$user->roleLabel()}" : null,
                $before && $before[1] !== $user->is_active ? ($user->is_active ? 'reactivated' : 'deactivated') : null,
                $added ? 'extra permissions added: '.SaveRole::labels($added) : null,
                $removed ? 'extra permissions removed: '.SaveRole::labels($removed) : null,
            ]);
            $this->log($before === null ? 'Added user' : 'Changed user', implode('; ', $parts));

            return $user;
        });
    }
}
