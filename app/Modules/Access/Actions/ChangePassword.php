<?php

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Support\Actions\Action;
use Illuminate\Support\Facades\Auth;

/** Sets a new password and signs the user out of their other browsers and devices. */
class ChangePassword extends Action
{
    public function handle(User $user, string $password): void
    {
        $user->update(['password' => $password]);
        Auth::logoutOtherDevices($password);
        $user->tokens()->delete();
        session()->forget('weak_password');
        $this->log('Changed password', $user->name);
    }
}
