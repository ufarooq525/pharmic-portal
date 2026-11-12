<?php

namespace App\Modules\Imports\Models;

use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Enums\ReportLayout;
use App\Modules\Distribution\Models\Distributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'layout' => ReportLayout::class,
            'period' => 'date', 'period_from' => 'date', 'period_to' => 'date', 'confirmed_at' => 'datetime',
            'checks' => 'array', 'customers' => 'array',
            'file_units' => 'float', 'file_value' => 'float', 'parsed_units' => 'float', 'parsed_value' => 'float',
        ];
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === ImportStatus::Confirmed;
    }
}
