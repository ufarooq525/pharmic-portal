<?php

namespace App\Modules\Sales\Http\Requests;

use App\Support\Http\Requests\FormRequest;

class StoreStockReceiptRequest extends FormRequest
{
    protected array $roles = ['accounts'];

    public function rules(): array
    {
        return [
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
            'lines' => ['nullable', 'array', 'max:50'],
            'lines.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.units' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array{received_on: string, reference: ?string, supplier: ?string, note: ?string} */
    public function receiptDetails(): array
    {
        return $this->safe()->only(['received_on', 'reference', 'supplier', 'note']) + ['reference' => null, 'supplier' => null, 'note' => null];
    }

    /** Rows typed by hand; empty rows are skipped. @return list<array{product_id: int, units: float, unit_cost: ?float}> */
    public function typedLines(): array
    {
        return collect($this->validated('lines') ?? [])
            ->filter(fn ($l) => ! empty($l['product_id']) && ! empty($l['units']))
            ->map(fn ($l) => ['product_id' => (int) $l['product_id'], 'units' => (float) $l['units'], 'unit_cost' => isset($l['unit_cost']) && $l['unit_cost'] !== '' ? (float) $l['unit_cost'] : null])
            ->values()->all();
    }
}
