<?php

namespace App\Modules\Ledger\Http\Requests;

use App\Modules\Ledger\Enums\PaymentMethod;
use App\Support\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    protected array $roles = ['accounts'];

    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->canSeeDistributor((int) $this->route('distributor')->id);
    }

    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
