<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Models\User;
use App\Modules\Access\Actions\SaveUser;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Requests\SaveUserRequest;
use App\Modules\Access\Repositories\UserRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController
{
    public function index(Request $request, UserRepository $users, DistributorRepository $distributors, MedicalRepRepository $reps): View
    {
        return view('access::users', [
            'tab' => $request->query('tab') === 'permissions' ? 'permissions' : 'users',
            'users' => $users->forAdmin(),
            'roles' => Role::cases(),
            'permissions' => self::PERMISSIONS,
            'distributors' => $distributors->visible(null, ['id', 'name']),
            'reps' => $reps->withTerritory(activeOnly: true),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function store(SaveUserRequest $request, SaveUser $save): RedirectResponse
    {
        $user = $save->handle($request->safe()->only(['name', 'email', 'password']) + $request->scope());

        return back()->with('status', "{$user->name} can now sign in.");
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $save): RedirectResponse
    {
        try {
            $save->handle($request->scope() + ['is_active' => $request->boolean('is_active')] + array_filter(['password' => $request->validated('password')]), $user, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()->route('users.index')->with('status', "{$user->name} updated.");
    }

    /**
     * Module or action → [owner, accounts, data entry, viewer, medical rep], as in the approved design.
     * Enforced by the "role:" middleware on each route; medical reps only get their "My territory" page.
     */
    public const PERMISSIONS = [
        'Dashboard' => ['Full', 'View', 'View', 'View', 'Own territory'],
        'Upload distributor files' => ['Full', 'Edit', 'Edit', 'None', 'None'],
        'Review and fix imports' => ['Full', 'Edit', 'Edit', 'View', 'None'],
        'Confirm import (posts stock)' => ['Full', 'Full', 'None', 'None', 'None'],
        'Undo a confirmed import' => ['Full', 'None', 'None', 'None', 'None'],
        'Customer register' => ['Full', 'Edit', 'Edit', 'View', 'Own territory'],
        'Inventory and purchases' => ['Full', 'Edit', 'View', 'View', 'None'],
        'Reports and exports' => ['Full', 'Full', 'View', 'View', 'Own territory'],
        'Invoices: create and issue' => ['Full', 'Full', 'None', 'None', 'None'],
        'Invoices: cancel, credit note' => ['Full', 'None', 'None', 'None', 'None'],
        'Distributor ledger, payments' => ['Full', 'Edit', 'None', 'View', 'None'],
        'MR payroll and payslips' => ['Full', 'Edit', 'None', 'None', 'Own territory'],
        'Products, prices, tax rates' => ['Full', 'View', 'View', 'View', 'None'],
        'WhatsApp sharing' => ['Full', 'Full', 'Edit', 'None', 'Own territory'],
        'Users and roles' => ['Full', 'None', 'None', 'None', 'None'],
        'Activity log' => ['Full', 'View', 'None', 'None', 'None'],
    ];
}
