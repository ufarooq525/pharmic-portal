<?php

namespace App\Modules\Distribution\Http\Requests;

use App\Modules\Access\Enums\Permission;
use App\Support\Http\Requests\FormRequest;

class AssignRepsRequest extends FormRequest
{
    protected ?Permission $permission = Permission::CustomersAssign;

    public function rules(): array
    {
        return [
            'rep_1' => ['nullable', 'exists:medical_reps,id'],
            'rep_2' => ['nullable', 'exists:medical_reps,id', 'different:rep_1'],
        ];
    }

    /** @return list<int> */
    public function repIds(): array
    {
        return array_map('intval', array_values(array_filter([$this->validated('rep_1'), $this->validated('rep_2')])));
    }
}
