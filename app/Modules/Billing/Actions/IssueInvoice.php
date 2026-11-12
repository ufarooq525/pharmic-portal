<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Enums\InvoiceKind;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use DomainException;

/** Freezes a month's confirmed sales for a distributor or one customer into a numbered invoice. */
class IssueInvoice extends Action
{
    public function __construct(private readonly InvoiceRepository $invoices) {}

    public function handle(Distributor $distributor, ?Customer $customer, CarbonImmutable $period, User $user): Invoice
    {
        $lines = $this->invoices->lines($distributor, $customer, $period);
        if ($lines === []) {
            throw new DomainException('There are no confirmed sales to invoice for '.$period->format('F Y').'.');
        }

        $invoice = $this->transaction(function () use ($distributor, $customer, $period, $user, $lines) {
            if ($this->invoices->issuedFor($distributor, $customer, $period)) {
                throw new DomainException('An invoice for this account and month is already issued. Cancel it with a credit note first.');
            }

            $invoice = $this->invoices->create($this->invoices->totals($lines) + [
                'number' => $this->invoices->nextNumber(InvoiceKind::Invoice, $period),
                'kind' => InvoiceKind::Invoice,
                'distributor_id' => $distributor->id,
                'customer_id' => $customer?->id,
                'period' => $period->startOfMonth(),
                'status' => InvoiceStatus::Issued,
                'issued_by' => $user->id,
                'issued_at' => now(),
            ]);
            $invoice->lines()->createMany($lines);

            return $invoice;
        });

        $this->log('Issued invoice', "{$invoice->number} to {$invoice->billTo()}, ".fmt_rs($invoice->total));

        return $invoice;
    }
}
