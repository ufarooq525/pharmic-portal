<?php

namespace App\Modules\Imports\Http\Requests;

use App\Modules\Catalog\Models\Product;
use App\Support\Http\Requests\FormRequest;

class LinkProductNameRequest extends FormRequest
{
    protected array $roles = ['accounts', 'data_entry'];

    /** Used by the web screen and the API alike, so both honour the user's distributor scope. */
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->canSeeDistributor((int) $this->route('import')->distributor_id);
    }

    public function rules(): array
    {
        return [
            'product_name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'exists:products,id'],
        ];
    }

    public function product(): Product
    {
        return Product::findOrFail($this->validated('product_id'));
    }
}
