<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    public const INVOICE = 'invoice';

    public const CREDIT_NOTE = 'credit_note';

    public const ISSUED = 'issued';

    public const CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period' => 'date', 'issued_at' => 'datetime', 'cancelled_at' => 'datetime',
            'units' => 'float', 'bonus' => 'float', 'value' => 'float', 'sales_tax' => 'float', 'withholding_tax' => 'float', 'total' => 'float',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(self::class, 'credit_note_for_id');
    }

    public function creditNoteFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_note_for_id');
    }

    public function isCreditNote(): bool
    {
        return $this->kind === self::CREDIT_NOTE;
    }

    public function billTo(): string
    {
        return $this->customer
            ? $this->customer->name.($this->customer->area ? ', '.$this->customer->area : '')
            : $this->distributor->name.', '.$this->distributor->city;
    }
}
