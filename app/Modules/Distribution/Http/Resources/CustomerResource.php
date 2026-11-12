<?php

namespace App\Modules\Distribution\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Distribution\Models\Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'area' => $this->area,
            'first_seen' => $this->first_seen?->format('Y-m'),
            'last_seen' => $this->last_seen?->format('Y-m'),
            'distributor' => new DistributorResource($this->whenLoaded('distributor')),
            'medical_reps' => MedicalRepResource::collection($this->whenLoaded('medicalReps')),
        ];
    }
}
