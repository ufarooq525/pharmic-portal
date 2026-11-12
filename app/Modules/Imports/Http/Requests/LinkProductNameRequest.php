<?php

namespace App\Modules\Imports\Http\Requests;

use App\Modules\Catalog\Models\Product;
use App\Support\Http\Requests\FormRequest;

class LinkProductNameRequest extends FormRequest
{
    protected array $roles = ['accounts', 'data_entry'];

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
