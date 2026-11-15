<?php

namespace App\Modules\Imports\Http\Requests;

use App\Modules\Access\Enums\Permission;
use App\Support\Http\Requests\FormRequest;

class RevertImportRequest extends FormRequest
{
    protected ?Permission $permission = Permission::ImportsRevert;

    public function rules(): array
    {
        return ['remove_file' => ['sometimes', 'boolean']];
    }
}
