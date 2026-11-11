<?php

namespace App\Modules\Distribution\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['base_salary' => 'float', 'is_active' => 'boolean'];
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }
}
