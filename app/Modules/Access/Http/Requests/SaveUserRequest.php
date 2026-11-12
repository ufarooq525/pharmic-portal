<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Enums\Role;
use App\Support\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Adding a user (name, email, password) or changing one (role, scope, status). */
class SaveUserRequest extends FormRequest
{
    protected array $roles = ['owner'];

    public function rules(): array
    {
        $creating = $this->route('user') === null;

        return [
            'name' => [Rule::requiredIf($creating), 'string', 'max:100'],
            'email' => [Rule::requiredIf($creating), 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => [Rule::requiredIf($creating), 'nullable', Password::min(10)],
            'is_active' => [Rule::requiredIf(! $creating), 'boolean'],
            'role' => ['required', Rule::enum(Role::class)],
            'medical_rep_id' => ['nullable', 'required_if:role,medical_rep', 'exists:medical_reps,id'],
            'distributor_ids' => ['nullable', 'array'],
            'distributor_ids.*' => ['integer', 'exists:distributors,id'],
        ];
    }

    /** Role and data scope: a medical rep is tied to their rep record, other roles may be limited to some distributors. */
    public function scope(): array
    {
        $role = Role::from($this->validated('role'));
        $isRep = $role === Role::MedicalRep;
        $ids = $this->validated('distributor_ids');

        return [
            'role' => $role,
            'medical_rep_id' => $isRep ? (int) $this->validated('medical_rep_id') : null,
            'distributor_ids' => ! $isRep && $role !== Role::Owner && ! empty($ids) ? array_map('intval', $ids) : null,
        ];
    }
}
