<?php

namespace App\Modules\Imports\Actions;

use App\Models\User;
use App\Modules\Billing\Actions\CancelInvoice;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Models\Import;
use App\Modules\Sales\Models\SaleLine;
use App\Modules\Sales\Models\StockMovement;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RevertImport extends Action
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly CancelInvoice $cancelInvoice,
    ) {}

    /**
     * Undo a confirmed import cleanly, all or nothing:
     *  - its sale lines and stock movements are removed (sold and bonus units go back into stock);
     *  - any invoice already issued for that distributor and month is cancelled with a credit note;
     *  - customers this file added, that have no other sales or invoices, leave the register again;
     *  - every other customer's "last seen" month is worked out again from their remaining sales.
     * The import goes back to review, so it can be fixed and confirmed again, or discarded.
     *
     * @return array{lines: int, units: float, invoices: list<string>, customers: int}
     */
    public function handle(Import $import, ?User $user = null, bool $log = true): array
    {
        $done = DB::transaction(function () use ($import, $user) {
            $period = CarbonImmutable::parse($import->period);
            $customerIds = SaleLine::where('import_id', $import->id)->distinct()->pluck('customer_id');
            $units = -1 * (float) StockMovement::where('import_id', $import->id)->sum('units');

            $lines = SaleLine::where('import_id', $import->id)->delete();
            StockMovement::where('import_id', $import->id)->delete();

            // An issued invoice for this month no longer matches the sales: reverse it with a credit note.
            $cancelled = [];
            foreach ($this->invoices->issuedForDistributorMonth($import->distributor_id, $period) as $invoice) {
                $note = $this->cancelInvoice->handle($invoice, "Import undone: {$import->original_name}", $user ?? auth()->user());
                $cancelled[] = "{$invoice->number} (credit note {$note->number})";
            }

            // Two grouped queries for all the file's customers, not two per customer.
            $lastSales = SaleLine::whereIn('customer_id', $customerIds)->groupBy('customer_id')->selectRaw('customer_id, MAX(period) AS last')->pluck('last', 'customer_id');
            $invoiced = Invoice::whereIn('customer_id', $customerIds)->distinct()->pluck('customer_id')->flip();

            $removed = 0;
            foreach (Customer::whereIn('id', $customerIds)->get() as $customer) {
                $lastSale = $lastSales[$customer->id] ?? null;
                if ($lastSale === null && $customer->first_seen->isSameMonth($period) && ! $invoiced->has($customer->id)) {
                    $customer->medicalReps()->detach();
                    $customer->delete();
                    $removed++;
                } else {
                    $customer->update(['last_seen' => $lastSale ? CarbonImmutable::parse($lastSale) : $customer->first_seen]);
                }
            }

            $import->update(['status' => ImportStatus::NeedsReview, 'confirmed_by' => null, 'confirmed_at' => null]);

            return ['lines' => $lines, 'units' => $units, 'invoices' => $cancelled, 'customers' => $removed];
        });

        if ($log) {
            $this->log('Reverted import', "{$import->distributor->name}, {$import->period->format('F Y')}: ".fmt_num($done['units']).' units back in stock'
                .($done['invoices'] ? ', cancelled '.implode(', ', $done['invoices']) : ''));
        }

        return $done;
    }

}
