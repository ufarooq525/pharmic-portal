<?php

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Repositories\UserRepository;
use App\Support\Actions\Action;
use DomainException;

/** Adds a user, or changes a user's role, scope and status. The owner cannot lock themselves out. */
class SaveUser extends Action
{
    public function __construct(private readonly UserRepository $users) {}

    public function handle(array $attributes, ?User $user = null, ?User $actingUser = null): User
    {
        if ($user === null) {
            $user = $this->users->create($attributes + ['is_active' => true]);
            $this->log('Added user', "{$user->name} ({$user->email}) as ".$user->role->label());

            return $user;
        }

        if ($actingUser?->is($user) && ($attributes['role'] !== Role::Owner || ! $attributes['is_active'])) {
            throw new DomainException('You cannot remove your own owner access.');
        }

        $this->users->update($user, $attributes);
        $this->log('Changed user', "{$user->name}: ".$user->role->label().($user->is_active ? ', active' : ', deactivated'));

        return $user;
    }
}
