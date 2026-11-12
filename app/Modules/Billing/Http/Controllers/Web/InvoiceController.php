<?php

namespace App\Modules\Billing\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Support\Period;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController
{
    public function index(Request $request, InvoiceService $invoices): View
    {
        $period = Period::fromRequest($request);
        $allowed = $request->user()->visibleDistributorIds();
        $distributors = Distributor::orderBy('name')->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))->get();

        // Customers who bought this month, grouped by distributor, for the "Bill to" picker.
        $customerIds = DB::table('sale_lines')->whereBetween('period', [Period::start($period), Period::end($period)])->distinct()->pluck('customer_id');
        $customers = Customer::whereIn('id', $customerIds)->whereIn('distributor_id', $distributors->pluck('id'))->orderBy('name')->get()->groupBy('distributor_id');

        [$distributor, $customer] = $this->billTo((string) $request->query('to'), $distributors);
        $issued = $distributor ? $invoices->issued($distributor, $customer, $period) : null;
        $lines = $issued ? $issued->lines->map->toArray()->all() : ($distributor ? $invoices->lines($distributor, $customer, $period) : []);

        return view('billing::index', [
            'period' => $period,
            'distributors' => $distributors,
            'customers' => $customers,
            'to' => (string) $request->query('to'),
            'distributor' => $distributor,
            'customer' => $customer,
            'issued' => $issued?->load(['issuedBy:id,name', 'creditNote']),
            'lines' => $lines,
            'totals' => $issued ? $issued->only(['units', 'bonus', 'value', 'sales_tax', 'withholding_tax', 'total']) : $invoices->totals($lines),
            'month' => Invoice::with(['distributor:id,name,city', 'customer:id,name,area'])
                ->whereBetween('period', [Period::start($period), Period::end($period)])
                ->when($allowed !== null, fn ($q) => $q->whereIn('distributor_id', $allowed))
                ->orderBy('number')->paginate(25)->withQueryString()->fragment('issued'),
            'canIssue' => $request->user()->hasRole('accounts'),
            'canCancel' => $request->user()->hasRole('owner'),
        ]);
    }

    public function store(Request $request, InvoiceService $invoices): RedirectResponse
    {
        $period = CarbonImmutable::createFromFormat('!Y-m', (string) $request->validate(['period' => ['required', 'date_format:Y-m']])['period']);
        [$distributor, $customer] = $this->billTo((string) $request->input('to'), Distributor::all());
        abort_unless($distributor, 422, 'Pick who the invoice is for.');
        $this->authorizeDistributor($request, $distributor->id);

        try {
            $invoice = $invoices->issue($distributor, $customer, $period, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        ActivityLog::record('Issued invoice', "{$invoice->number} to {$invoice->billTo()}, ".fmt_rs($invoice->total));

        return back()->with('status', "Invoice {$invoice->number} is issued and locked. Corrections now go through a credit note.");
    }

    public function cancel(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        try {
            $note = $invoices->cancel($invoice, $reason, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        ActivityLog::record('Cancelled invoice', "{$invoice->number} with credit note {$note->number}: {$reason}");

        return back()->with('status', "{$invoice->number} is cancelled. Credit note {$note->number} reverses it; you can now issue a new invoice.");
    }

    /** A clean page to print or save as PDF. */
    public function print(Request $request, Invoice $invoice): View
    {
        $this->authorizeDistributor($request, $invoice->distributor_id);

        return view('billing::print', ['invoice' => $invoice->load(['lines', 'distributor', 'customer', 'issuedBy:id,name', 'creditNoteFor:id,number'])]);
    }

    /** "d:5" is a distributor account, "c:42" a single customer. */
    private function billTo(string $to, $distributors): array
    {
        if (preg_match('/^c:(\d+)$/', $to, $m) && ($customer = Customer::find($m[1])) && $distributors->contains('id', $customer->distributor_id)) {
            return [$distributors->firstWhere('id', $customer->distributor_id), $customer];
        }
        if (preg_match('/^d:(\d+)$/', $to, $m)) {
            return [$distributors->firstWhere('id', (int) $m[1]), null];
        }

        return [null, null];
    }

    private function authorizeDistributor(Request $request, int $distributorId): void
    {
        $allowed = $request->user()->visibleDistributorIds();
        abort_if($allowed !== null && ! in_array($distributorId, $allowed, true), 403);
    }
}
