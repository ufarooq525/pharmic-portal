<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use App\Models\User;
use App\Modules\Access\Enums\Role;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController
{
    public function index(Request $request): View
    {
        return view('access::users', [
            'tab' => $request->query('tab') === 'permissions' ? 'permissions' : 'users',
            'users' => User::with('medicalRep.territory')->orderByDesc('is_active')->orderBy('name')->get(),
            'roles' => Role::cases(),
            'permissions' => self::PERMISSIONS,
            'distributors' => Distributor::orderBy('name')->get(['id', 'name']),
            'reps' => MedicalRep::with('territory')->where('is_active', true)->orderBy('name')->get(),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', Password::min(10)],
        ] + $this->scopeRules());

        User::create($this->scope($data) + ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_active' => true]);
        ActivityLog::record('Added user', "{$data['name']} ({$data['email']}) as ".Role::from($data['role'])->label());

        return back()->with('status', "{$data['name']} can now sign in.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']] + $this->scopeRules());

        // The owner cannot lock themselves out.
        if ($user->is($request->user()) && ($data['role'] !== Role::Owner->value || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'You cannot remove your own owner access.']);
        }

        $user->update($this->scope($data) + ['is_active' => (bool) $data['is_active']]);
        ActivityLog::record('Changed user', "{$user->name}: ".Role::from($data['role'])->label().($data['is_active'] ? ', active' : ', deactivated'));

        return redirect()->route('users.index')->with('status', "{$user->name} updated.");
    }

    private function scopeRules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
            'medical_rep_id' => ['nullable', 'required_if:role,medical_rep', 'exists:medical_reps,id'],
            'distributor_ids' => ['nullable', 'array'],
            'distributor_ids.*' => ['integer', 'exists:distributors,id'],
        ];
    }

    /** A medical rep is tied to their rep record; other roles may be limited to some distributors. */
    private function scope(array $data): array
    {
        $isRep = $data['role'] === Role::MedicalRep->value;

        return [
            'role' => $data['role'],
            'medical_rep_id' => $isRep ? (int) $data['medical_rep_id'] : null,
            'distributor_ids' => ! $isRep && $data['role'] !== Role::Owner->value && ! empty($data['distributor_ids'])
                ? array_map('intval', $data['distributor_ids']) : null,
        ];
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
