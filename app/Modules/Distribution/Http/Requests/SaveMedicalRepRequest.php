<?php

namespace App\Modules\Distribution\Http\Requests;

use App\Support\Http\Requests\FormRequest;

class SaveMedicalRepRequest extends FormRequest
{
    protected array $roles = ['accounts'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'territory' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'base_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
