<?php

namespace App\Modules\Billing\Http\Requests;

use App\Support\Http\Requests\FormRequest;

class CancelInvoiceRequest extends FormRequest
{
    protected array $roles = ['owner'];

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
