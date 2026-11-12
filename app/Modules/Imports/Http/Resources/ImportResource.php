<?php

namespace App\Modules\Imports\Http\Resources;

use App\Modules\Distribution\Http\Resources\DistributorResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Imports\Models\Import */
class ImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period->format('Y-m'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'layout' => $this->layout?->value,
            'original_name' => $this->original_name,
            'distributor_in_file' => $this->distributor_in_file,
            'file_units' => (float) $this->file_units,
            'file_value' => (float) $this->file_value,
            'parsed_units' => (float) $this->parsed_units,
            'parsed_value' => (float) $this->parsed_value,
            'checks' => $this->checks,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'distributor' => new DistributorResource($this->whenLoaded('distributor')),
            'unmapped' => $this->rows()->whereNull('product_id')->distinct()->pluck('product_name'),
        ];
    }
}
