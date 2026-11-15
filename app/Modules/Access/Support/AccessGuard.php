<?php

namespace App\Modules\Access\Support;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use DomainException;

/**
 * The rules that keep access safe when roles and people are changed:
 *  - nobody can give a permission they do not have themselves (the owner has them all);
 *  - the Owner role cannot be changed, and only an owner can make or change an owner;
 *  - there is always at least one active owner.
 * Each rule throws a DomainException with a message for the person making the change.
 */
class AccessGuard
{
    /** @param list<string> $permissions values being added */
    public function assertCanGrant(User $actor, array $permissions): void
    {
        if ($actor->isOwner()) {
            return;
        }

        $missing = array_diff($permissions, $actor->permissionValues());
        if ($missing !== []) {
            $labels = array_map(fn ($v) => '“'.Permission::from($v)->label().'”', array_values($missing));
            throw new DomainException('You can only give permissions you have yourself. You don’t have '.implode(', ', $labels).'.');
        }
    }

    public function assertRoleEditable(Role $role): void
    {
        if ($role->is_owner) {
            throw new DomainException('The Owner role always has every permission and cannot be changed or deleted.');
        }
    }

    public function assertCanManagePerson(User $actor, ?User $person, ?Role $newRole): void
    {
        if ($actor->isOwner()) {
            return;
        }
        if ($person?->isOwner()) {
            throw new DomainException('Only an owner can change an owner’s account.');
        }
        if ($newRole?->is_owner) {
            throw new DomainException('Only an owner can make someone an owner.');
        }
    }

    /** Refuses a change that would leave no active owner. */
    public function assertOwnerRemains(User $person, bool $staysActiveOwner): void
    {
        if ($staysActiveOwner || ! $person->isOwner() || ! $person->is_active) {
            return;
        }

        $others = User::query()
            ->whereKeyNot($person->getKey())
            ->where('is_active', true)
            ->where('login_type', 'staff')
            ->whereHas('role', fn ($q) => $q->where('is_owner', true))
            ->exists();

        if (! $others) {
            throw new DomainException('There must always be at least one active owner. Make someone else an owner first.');
        }
    }
}
