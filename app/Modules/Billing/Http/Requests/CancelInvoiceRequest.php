<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Access\Enums\Permission;
use App\Support\Http\Requests\FormRequest;

class CancelInvoiceRequest extends FormRequest
{
    protected ?Permission $permission = Permission::InvoicesCancel;

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
