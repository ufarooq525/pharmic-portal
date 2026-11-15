<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Modules\Access\Actions\DeleteRole;
use App\Modules\Access\Actions\SaveRole;
use App\Modules\Access\Http\Requests\SaveRoleRequest;
use App\Modules\Access\Models\Role;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/** Adding, changing and deleting roles. The list and forms are on the Roles tab of UserController::index. */
class RoleController
{
    public function store(SaveRoleRequest $request, SaveRole $save): RedirectResponse
    {
        $role = $this->attempt(fn () => $save->handle($request->safe()->only(['name', 'description']), $request->ticked(), $request->user()));

        return redirect()->route('users.index', ['tab' => 'roles'])->with('status', "Role “{$role->name}” added.");
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRole $save): RedirectResponse
    {
        $this->attempt(fn () => $save->handle($request->safe()->only(['name', 'description']), $request->ticked(), $request->user(), $role));

        return redirect()->route('users.index', ['tab' => 'roles'])->with('status', "Role “{$role->name}” saved. People with it get the change on their next page.");
    }

    public function destroy(Role $role, DeleteRole $delete): RedirectResponse
    {
        try {
            $delete->handle($role);
        } catch (DomainException $e) {
            return redirect()->route('users.index', ['tab' => 'roles'])->withErrors(['access' => $e->getMessage()]);
        }

        return redirect()->route('users.index', ['tab' => 'roles'])->with('status', "Role “{$role->name}” deleted.");
    }

    private function attempt(callable $work): Role
    {
        try {
            return $work();
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['access' => $e->getMessage()]);
        }
    }
}
