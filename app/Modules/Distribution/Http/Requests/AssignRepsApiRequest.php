<?php

namespace App\Modules\Distribution\Http\Requests;

use App\Support\Http\Requests\FormRequest;
use Illuminate\Validation\Validator;

/** API form: up to two reps with explicit shares that add up to 100%. */
class AssignRepsApiRequest extends FormRequest
{
    protected array $roles = ['accounts', 'data_entry'];

    public function rules(): array
    {
        return [
            'reps' => ['required', 'array', 'min:1', 'max:2'],
            'reps.*.medical_rep_id' => ['required', 'distinct', 'exists:medical_reps,id'],
            'reps.*.share_percent' => ['required', 'integer', 'between:1,100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (array_sum(array_column((array) $this->input('reps'), 'share_percent')) !== 100) {
                $v->errors()->add('reps', 'Shares must add up to 100%.');
            }
        }];
    }
}
