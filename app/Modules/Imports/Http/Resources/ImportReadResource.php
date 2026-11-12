<?php

namespace App\Modules\Imports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the upload screen needs after a file is read: where to go next and the four reading steps
 * (like the approved prototype), each with its result and whether its check passed.
 *
 * @mixin \App\Modules\Imports\Models\Import
 */
class ImportReadResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $checks = collect($this->checks)->keyBy('key');
        $passed = fn (string ...$keys) => collect($keys)->every(fn ($k) => $checks[$k]['ok'] ?? true);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'redirect' => route('imports.show', $this->resource),
            'steps' => [
                ['label' => 'Recognising the layout', 'result' => $this->layout?->label(), 'ok' => $passed('format')],
                ['label' => 'Reading the header', 'result' => '“'.$this->distributor_in_file.'” · '.($this->period_from?->format('j M Y') ?? 'no dates').' to '.($this->period_to?->format('j M Y') ?? '?'), 'ok' => $passed('distributor', 'period')],
                ['label' => 'Reading customers and lines', 'result' => count($this->customers).' customers · '.$this->rows()->count().' product lines', 'ok' => true],
                ['label' => 'Checking against the grand total', 'result' => fmt_num($this->parsed_units).' units read · file says '.fmt_num($this->file_units), 'ok' => $passed('totals')],
            ],
        ];
    }
}
