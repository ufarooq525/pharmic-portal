<?php

namespace App\Modules\Imports\Http\Requests;

use App\Modules\Access\Enums\Permission;
use App\Support\Http\Requests\FormRequest;

class AttachStockReportRequest extends FormRequest
{
    protected ?Permission $permission = Permission::ImportsUpload;

    public function rules(): array
    {
        return [
            'stock_report' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return ['stock_report.required' => 'Choose the Sales & Stock report PDF.', 'stock_report.mimes' => 'The Sales & Stock report must be a PDF.'];
    }
}
