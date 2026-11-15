<?php

namespace App\Support\Http\Requests;

use App\Modules\Access\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;

/**
 * Base for the portal's form requests. Set $permission to the permission needed to send it (the
 * owner always has it); routes check it too, this keeps the rule next to the validation.
 */
abstract class FormRequest extends BaseFormRequest
{
    protected ?Permission $permission = null;

    public function authorize(): bool
    {
        return $this->permission === null || (bool) $this->user()?->hasPermission($this->permission);
    }

    /** Distributors the signed-in user may see, or null for all. */
    protected function visibleDistributorIds(): ?array
    {
        return $this->user()?->visibleDistributorIds();
    }
}
