<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Enums\LoginType;
use App\Modules\Access\Enums\Permission;
use App\Support\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Adding a person (name, email, password) or changing one (login type, role, extras, data limits, status). */
class SaveUserRequest extends FormRequest
{
    protected ?Permission $permission = Permission::UsersManage;

    public function rules(): array
    {
        $creating = $this->route('user') === null;

        return [
            'name' => [Rule::requiredIf($creating), 'string', 'max:100'],
            'email' => [Rule::requiredIf($creating), 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => [Rule::requiredIf($creating), 'nullable', Password::min(10)],
            'is_active' => [Rule::requiredIf(! $creating), 'boolean'],
            'login_type' => ['required', Rule::enum(LoginType::class)],
            'role_id' => ['nullable', 'required_if:login_type,staff', 'integer', 'exists:roles,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
            'medical_rep_id' => ['nullable', 'required_if:login_type,medical_rep', 'integer', 'exists:medical_reps,id'],
            'distributor_ids' => ['nullable', 'array'],
            'distributor_ids.*' => ['integer', 'exists:distributors,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.required_if' => 'Choose a role.',
            'medical_rep_id.required_if' => 'Choose which medical rep this login belongs to.',
        ];
    }

    /** Login type, role and data limits; SaveUser clears what does not apply to the login type. */
    public function access(): array
    {
        $ids = $this->validated('distributor_ids');

        return [
            'login_type' => $this->validated('login_type'),
            'role_id' => $this->validated('role_id') ? (int) $this->validated('role_id') : null,
            'medical_rep_id' => $this->validated('medical_rep_id') ? (int) $this->validated('medical_rep_id') : null,
            'distributor_ids' => empty($ids) ? null : array_map('intval', $ids),
        ];
    }

    /** @return list<string> */
    public function extras(): array
    {
        return array_values($this->validated('permissions') ?? []);
    }
}
