<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use App\Models\User;
use App\Modules\Access\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController
{
    public function index(): View
    {
        return view('access::users', [
            'users' => User::orderByDesc('is_active')->orderBy('name')->get(),
            'roles' => Role::cases(),
            'permissions' => self::PERMISSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', Password::min(10)],
        ]);

        User::create($data + ['is_active' => true]);
        ActivityLog::record('Added user', "{$data['name']} ({$data['email']}) as ".Role::from($data['role'])->label());

        return back()->with('status', "{$data['name']} can now sign in.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['required', 'boolean'],
        ]);

        // The owner cannot lock themselves out.
        if ($user->is($request->user()) && ($data['role'] !== Role::Owner->value || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'You cannot remove your own owner access.']);
        }

        $user->update($data);
        ActivityLog::record('Changed user', "{$user->name}: ".Role::from($data['role'])->label().($data['is_active'] ? ', active' : ', deactivated'));

        return back()->with('status', "{$user->name} updated.");
    }

    /**
     * What each role can do. Enforced by the "role:" middleware on each route; shown on the Users page.
     * Medical rep access to their own territory comes with the reps' self-service screens (phase 2).
     */
    public const PERMISSIONS = [
        'See dashboard, customers, inventory and reports' => ['owner', 'accounts', 'data_entry', 'viewer'],
        'Upload distributor files and map product names' => ['owner', 'accounts', 'data_entry'],
        'Confirm an import (posts stock and sales)' => ['owner', 'accounts'],
        'Assign medical reps to customers' => ['owner', 'accounts'],
        'Change prices, sales tax and withholding tax' => ['owner'],
        'Manage users and roles' => ['owner'],
    ];
}
