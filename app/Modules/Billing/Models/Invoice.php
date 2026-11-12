<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\InvoiceKind;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Models\User;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceKind::class, 'status' => InvoiceStatus::class, 'period' => 'date', 'issued_at' => 'datetime', 'cancelled_at' => 'datetime',
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
        return $this->kind === InvoiceKind::CreditNote;
    }

    public function billTo(): string
    {
        return $this->customer
            ? $this->customer->name.($this->customer->area ? ', '.$this->customer->area : '')
            : $this->distributor->name.', '.$this->distributor->city;
    }
}
