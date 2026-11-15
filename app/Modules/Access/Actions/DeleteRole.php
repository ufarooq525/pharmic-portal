<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Support\AccessGuard;
use App\Support\Actions\Action;
use DomainException;

/** Deletes a role nobody has. The Owner role can never be deleted. */
class DeleteRole extends Action
{
    public function __construct(private readonly AccessGuard $guard) {}

    public function handle(Role $role): void
    {
        $this->guard->assertRoleEditable($role);

        $people = $role->users()->count();
        if ($people > 0) {
            throw new DomainException($people === 1
                ? 'One person has this role. Give them another role first.'
                : "{$people} people have this role. Give them another role first.");
        }

        $this->transaction(function () use ($role) {
            $role->delete();
            $this->log('Deleted role', $role->name);
        });
    }
}
