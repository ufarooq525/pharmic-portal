<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Repositories\DistributorRepository;
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
        $result = app(InvoiceRepository::class)->resolveBillTo(
            $this->validated('to'),
            app(DistributorRepository::class)->visible($this->visibleDistributorIds()),
        );
        abort_unless($result[0], 403);

        return $result;
    }
}
