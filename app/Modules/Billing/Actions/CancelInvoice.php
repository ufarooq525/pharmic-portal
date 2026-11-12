<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Enums\InvoiceKind;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use DomainException;

/** Cancels an issued invoice and issues a credit note that reverses every line. Returns the credit note. */
class CancelInvoice extends Action
{
    private const AMOUNTS = ['units', 'bonus', 'value', 'sales_tax', 'withholding_tax', 'total'];

    public function __construct(private readonly InvoiceRepository $invoices) {}

    public function handle(Invoice $invoice, string $reason, User $user): Invoice
    {
        if ($invoice->isCreditNote() || $invoice->status !== InvoiceStatus::Issued) {
            throw new DomainException('Only an issued invoice can be cancelled.');
        }

        $note = $this->transaction(function () use ($invoice, $reason, $user) {
            $note = $this->invoices->create($this->negate($invoice->only(self::AMOUNTS)) + [
                'number' => $this->invoices->nextNumber(InvoiceKind::CreditNote, CarbonImmutable::parse($invoice->period)),
                'kind' => InvoiceKind::CreditNote,
                'distributor_id' => $invoice->distributor_id,
                'customer_id' => $invoice->customer_id,
                'period' => $invoice->period,
                'status' => InvoiceStatus::Issued,
                'credit_note_for_id' => $invoice->id,
                'issued_by' => $user->id,
                'issued_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $note->lines()->createMany($invoice->loadMissing('lines')->lines->map(fn ($l) => $this->negate(
                $l->only(['product_id', 'product_name', 'units', 'bonus', 'rate', 'value', 'sales_tax_rate', 'sales_tax', 'withholding_tax_rate', 'withholding_tax', 'total']),
            ))->all());

            $this->invoices->update($invoice, ['status' => InvoiceStatus::Cancelled, 'cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $note;
        });

        $this->log('Cancelled invoice', "{$invoice->number} with credit note {$note->number}: {$reason}");

        return $note;
    }

    /** Flips the sign of the amount fields, leaving rates and names as they are. */
    private function negate(array $row): array
    {
        foreach (self::AMOUNTS as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = -1 * (float) $row[$key];
            }
        }

        return $row;
    }
}
