<?php

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\Enums\PaymentMethod;
use App\Models\User;
use App\Modules\Distribution\Models\Distributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'float', 'method' => PaymentMethod::class];
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
