<?php

namespace App\Modules\Imports\Http\Requests;

use App\Support\Http\Requests\FormRequest;

class RevertImportRequest extends FormRequest
{
    protected array $roles = ['owner'];

    public function rules(): array
    {
        return ['remove_file' => ['sometimes', 'boolean']];
    }
}
