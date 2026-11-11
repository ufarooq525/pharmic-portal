<?php

namespace App\Modules\Imports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role is checked by the route middleware
    }

    public function rules(): array
    {
        return [
            'distributor_id' => ['required', 'exists:distributors,id'],
            'period' => ['required', 'date_format:Y-m'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
