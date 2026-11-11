<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReceipt extends Model
{
    public const POSTED = 'posted';

    public const REVERTED = 'reverted';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'reverted_at' => 'datetime', 'total_units' => 'float'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'receipt_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === self::POSTED;
    }
}
