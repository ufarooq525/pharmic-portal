<?php

namespace App\Modules\Imports\Services;

use App\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Imports\Models\Import;
use App\Modules\Sales\Models\SaleLine;
use App\Modules\Sales\Models\StockMovement;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Posts a reviewed import: customer register, sale lines (with the month's prices and both tax
 * rates copied in), and stock movements. All or nothing.
 */
class ConfirmImport
{
    public function handle(Import $import, User $user): Import
    {
        if ($import->status !== Import::NEEDS_REVIEW) {
            throw new DomainException($import->isConfirmed() ? 'This import is already confirmed.' : 'This import is blocked by a failed check.');
        }

        if ($import->rows()->whereNull('product_id')->exists()) {
            throw new DomainException('Map every product name before confirming.');
        }

        return DB::transaction(function () use ($import, $user) {
            $period = CarbonImmutable::parse($import->period);

            // Re-importing a month replaces the earlier confirmed file; it never doubles it.
            Import::where('distributor_id', $import->distributor_id)->whereDate('period', $period)
                ->where('status', Import::CONFIRMED)->get()->each(fn (Import $old) => $this->reverse($old, $user));

            $distributor = $import->distributor;
            $customers = $this->registerCustomers($import, $period);
            $products = Product::with('rates')->findMany($import->rows()->distinct()->pluck('product_id'))->keyBy('id');

            foreach ($import->rows as $row) {
                $rate = $products[$row->product_id]->rateOn($period->endOfMonth())
                    ?? throw new DomainException("No price is set for {$products[$row->product_id]->name} in {$period->format('F Y')}.");

                $bonus = $distributor->bonusFor($row->bonus, $rate->schemeBonus($row->units));
                $valueTp = $row->value ?? round($row->units * $rate->trade_price, 2);
                $valueExfp = round($row->units * $rate->exfp_price, 2);

                SaleLine::create([
                    'import_id' => $import->id,
                    'distributor_id' => $import->distributor_id,
                    'customer_id' => $customers[$this->matchKey($row->customer_code, $row->customer_name, $row->area)],
                    'product_id' => $row->product_id,
                    'period' => $period,
                    'units' => $row->units,
                    'bonus_units' => $bonus,
                    'bonus_from_file' => $distributor->bonusIsFromFile($row->bonus),
                    'trade_price' => $row->trade_price ?? $rate->trade_price,
                    'exfp_price' => $rate->exfp_price,
                    'cost_price' => $rate->cost_price,
                    'value_tp' => $valueTp,
                    'value_exfp' => $valueExfp,
                    'sales_tax_rate' => $rate->sales_tax_rate,
                    'sales_tax' => round($valueTp * $rate->sales_tax_rate / 100, 2),
                    'withholding_tax_rate' => $rate->withholding_tax_rate,
                    'withholding_tax' => round($valueTp * $rate->withholding_tax_rate / 100, 2),
                    'incentive_rate' => $rate->incentive_rate,
                    'incentive' => round($valueExfp * $rate->incentive_rate / 100, 2),
                ]);

                // Sold and bonus units leave stock; a return (negative units) comes back in.
                StockMovement::create([
                    'product_id' => $row->product_id,
                    'import_id' => $import->id,
                    'type' => $row->units < 0 ? StockMovement::RETURN : StockMovement::SALE,
                    'units' => -($row->units + $bonus),
                    'moved_on' => $period->endOfMonth(),
                ]);
            }

            // The first confirmed file fixes the distributor's format; later files are checked against it.
            $distributor->layout ??= $import->layout;
            $distributor->header_name ??= $import->distributor_in_file ?: null;
            $distributor->save();

            $import->update(['status' => Import::CONFIRMED, 'confirmed_by' => $user->id, 'confirmed_at' => now()]);

            return $import;
        });
    }

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
    public function reverse(Import $import, ?User $user = null): array
    {
        return DB::transaction(function () use ($import, $user) {
            $period = CarbonImmutable::parse($import->period);
            $customerIds = SaleLine::where('import_id', $import->id)->distinct()->pluck('customer_id');
            $units = -1 * (float) StockMovement::where('import_id', $import->id)->sum('units');

            $lines = SaleLine::where('import_id', $import->id)->delete();
            StockMovement::where('import_id', $import->id)->delete();

            // An issued invoice for this month no longer matches the sales: reverse it with a credit note.
            $cancelled = [];
            $invoices = app(InvoiceService::class);
            Invoice::where('kind', Invoice::INVOICE)->where('status', Invoice::ISSUED)
                ->where('distributor_id', $import->distributor_id)->whereDate('period', $period->startOfMonth())
                ->get()->each(function (Invoice $invoice) use ($invoices, $import, $user, &$cancelled) {
                    $note = $invoices->cancel($invoice, "Import undone: {$import->original_name}", $user ?? auth()->user());
                    $cancelled[] = "{$invoice->number} (credit note {$note->number})";
                });

            $removed = 0;
            foreach (Customer::whereIn('id', $customerIds)->get() as $customer) {
                $lastSale = SaleLine::where('customer_id', $customer->id)->max('period');
                if ($lastSale === null && $customer->first_seen->isSameMonth($period) && ! Invoice::where('customer_id', $customer->id)->exists()) {
                    $customer->medicalReps()->detach();
                    $customer->delete();
                    $removed++;
                } else {
                    $customer->update(['last_seen' => $lastSale ? CarbonImmutable::parse($lastSale) : $customer->first_seen]);
                }
            }

            $import->update(['status' => Import::NEEDS_REVIEW, 'confirmed_by' => null, 'confirmed_at' => null]);

            return ['lines' => $lines, 'units' => $units, 'invoices' => $cancelled, 'customers' => $removed];
        });
    }

    /**
     * Adds new customers and marks existing ones as seen this month.
     *
     * @return array<string, int> match key => customer id
     */
    private function registerCustomers(Import $import, CarbonImmutable $period): array
    {
        $ids = [];

        foreach ($import->customers as $c) {
            $key = $this->matchKey($c['code'] ?? null, $c['name'], $c['area'] ?? null);
            $customer = Customer::firstOrCreate(
                ['distributor_id' => $import->distributor_id, 'match_key' => $key],
                ['code' => $c['code'] ?? null, 'name' => $c['name'], 'area' => $c['area'] ?? null, 'first_seen' => $period, 'last_seen' => $period],
            );

            if ($customer->last_seen->lt($period)) {
                $customer->update(['last_seen' => $period]);
            }

            $ids[$key] = $customer->id;
        }

        return $ids;
    }

    private function matchKey(?string $code, string $name, ?string $area): string
    {
        return Customer::matchKey($code, $name, $area);
    }
}
