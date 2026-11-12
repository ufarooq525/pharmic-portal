<?php

namespace App\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;

/**
 * Base for the portal's form requests. Set $roles to the roles allowed to send it (the owner is
 * always allowed); routes also check roles, this keeps the rule next to the validation.
 */
abstract class FormRequest extends BaseFormRequest
{
    /** @var list<string> */
    protected array $roles = [];

    public function authorize(): bool
    {
        return $this->roles === [] || (bool) $this->user()?->hasRole(...$this->roles);
    }

    /** Distributors the signed-in user may see, or null for all. */
    protected function visibleDistributorIds(): ?array
    {
        return $this->user()?->visibleDistributorIds();
    }
}
