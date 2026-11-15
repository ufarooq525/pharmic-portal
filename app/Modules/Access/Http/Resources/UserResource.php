<?php

namespace App\Modules\Access\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'login_type' => $this->login_type->value,
            'role' => $this->isMedicalRep() ? null : $this->role?->name,
            'role_label' => $this->roleLabel(),
            'permissions' => $this->permissionValues(),
            'distributor_ids' => $this->visibleDistributorIds(),
            'medical_rep_id' => $this->medical_rep_id,
        ];
    }
}
