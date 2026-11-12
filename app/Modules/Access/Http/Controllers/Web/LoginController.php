<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Modules\Access\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController
{
    public function show(): View
    {
        return view('access::login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        // Deactivated users cannot sign in, even with the right password.
        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These details do not match an active account.']);
        }

        $request->session()->regenerate();

        // Still on the password set at install time: remind on every page until it is changed.
        if (Hash::check('change-me-now', $request->user()->password)) {
            $request->session()->put('weak_password', true);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
