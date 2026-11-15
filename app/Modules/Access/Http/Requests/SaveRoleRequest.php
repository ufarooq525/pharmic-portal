<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Enums\Permission;
use App\Support\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;

/** A role's name, one-line description and ticked permissions. */
class SaveRoleRequest extends FormRequest
{
    protected ?Permission $permission = Permission::UsersManage;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:150'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'There is already a role with this name.'];
    }

    /** @return list<string> */
    public function ticked(): array
    {
        return array_values($this->validated('permissions') ?? []);
    }
}
