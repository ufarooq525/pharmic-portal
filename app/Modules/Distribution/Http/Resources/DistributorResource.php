<?php

namespace App\Modules\Distribution\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Distribution\Models\Distributor */
class DistributorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'phone' => $this->phone,
            'layout' => $this->layout?->value,
            'layout_label' => $this->layout?->label(),
            'header_name' => $this->header_name,
            'bonus_source' => $this->bonus_source->value,
            'opening_balance' => (float) $this->opening_balance,
        ];
    }
}
