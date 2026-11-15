<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Models\User;
use App\Modules\Access\Actions\SaveUser;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Http\Requests\SaveUserRequest;
use App\Modules\Access\Repositories\RoleRepository;
use App\Modules\Access\Repositories\UserRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use App\Support\Paginate;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** "Users & roles": the People tab (logins) and the Roles tab (see RoleController for saving roles). */
class UserController
{
    public function index(Request $request, UserRepository $users, RoleRepository $roles, DistributorRepository $distributors, MedicalRepRepository $reps): View
    {
        $roleList = $roles->forAdmin();
        $editingRole = $request->integer('role') ?: null;

        return view('access::users', [
            'tab' => $request->query('tab') === 'roles' ? 'roles' : 'people',
            'users' => $users->forAdmin(),
            'editUser' => $users->forEdit($request->integer('edit') ?: null),
            'roles' => $roleList,
            'rolesPage' => Paginate::collection($roleList, 25, 'roles_page'),
            'editRole' => $roleList->firstWhere('id', $editingRole),
            'editRolePeople' => $editingRole ? $users->namesWithRole($editingRole) : collect(),
            'rolePermissions' => $roleList->mapWithKeys(fn ($r) => [$r->id => $r->permissionValues()]),
            'permissionGroups' => Permission::grouped(),
            'distributors' => $distributors->visible(null, ['id', 'name']),
            'reps' => $reps->withTerritory(activeOnly: true),
            'actor' => $request->user(),
        ]);
    }

    public function store(SaveUserRequest $request, SaveUser $save): RedirectResponse
    {
        $user = $this->save(fn () => $save->handle($request->safe()->only(['name', 'email', 'password']) + $request->access(), $request->extras(), $request->user()));

        return redirect()->route('users.index')->with('status', "{$user->name} can now sign in.");
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $save): RedirectResponse
    {
        $attributes = $request->access() + ['is_active' => $request->boolean('is_active')] + array_filter(['password' => $request->validated('password')]);
        $this->save(fn () => $save->handle($attributes, $request->extras(), $request->user(), $user));

        return redirect()->route('users.index')->with('status', "{$user->name} updated.");
    }

    /** A broken rule (last owner, giving more than you have) is shown at the top of the form. */
    private function save(callable $work): User
    {
        try {
            return $work();
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['access' => $e->getMessage()]);
        }
    }
}
