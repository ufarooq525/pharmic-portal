<?php

namespace App\Modules\Access\Http\Requests;

use App\Support\Http\Requests\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'email'], 'password' => ['required', 'string']];
    }
}
