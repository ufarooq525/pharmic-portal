<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Support\Http\Requests\FormRequest;
use Carbon\CarbonImmutable;

/** "to" is "d:5" for a distributor account or "c:42" for one customer. */
class IssueInvoiceRequest extends FormRequest
{
    protected array $roles = ['accounts'];

    public function rules(): array
    {
        return [
            'to' => ['required', 'regex:/^[dc]:\d+$/'],
            'period' => ['required', 'date_format:Y-m'],
        ];
    }

    public function period(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->validated('period'));
    }

    /** @return array{0: Distributor, 1: ?Customer} */
    public function billTo(): array
    {
        [$type, $id] = explode(':', $this->validated('to'));
        $customer = $type === 'c' ? Customer::findOrFail($id) : null;
        $distributor = Distributor::findOrFail($customer?->distributor_id ?? $id);
        abort_unless($this->user()->canSeeDistributor($distributor->id), 403);

        return [$distributor, $customer];
    }
}
