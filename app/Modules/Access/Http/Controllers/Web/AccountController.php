<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Modules\Access\Actions\ChangePassword;
use App\Modules\Access\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** The signed-in person's own account: change password. */
class AccountController
{
    public function show(): View
    {
        return view('access::account');
    }

    public function updatePassword(ChangePasswordRequest $request, ChangePassword $change): RedirectResponse
    {
        $change->handle($request->user(), $request->validated('password'));

        return back()->with('status', 'Your password is changed. Other devices have been signed out.');
    }
}
