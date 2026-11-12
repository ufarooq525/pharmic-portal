<?php

namespace App\Modules\Distribution\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Distribution\Models\MedicalRep */
class MedicalRepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'territory' => $this->whenLoaded('territory', fn () => $this->territory->name),
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'share_percent' => $this->whenPivotLoaded('customer_medical_rep', fn () => (int) $this->pivot->share_percent),
        ];
    }
}
