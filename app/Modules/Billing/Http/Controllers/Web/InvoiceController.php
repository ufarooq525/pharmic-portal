<?php

namespace App\Modules\Billing\Http\Controllers\Web;

use App\Modules\Billing\Actions\CancelInvoice;
use App\Modules\Billing\Actions\IssueInvoice;
use App\Modules\Billing\Http\Requests\CancelInvoiceRequest;
use App\Modules\Billing\Http\Requests\IssueInvoiceRequest;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Repositories\CustomerRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Support\Period;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly DistributorRepository $distributors,
        private readonly CustomerRepository $customers,
    ) {}

    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);
        $allowed = $request->user()->visibleDistributorIds();
        $distributors = $this->distributors->visible($allowed);
        [$distributor, $customer] = $this->billTo((string) $request->query('to'), $distributors);
        $issued = $distributor ? $this->invoices->issuedFor($distributor, $customer, $period)?->load(['issuedBy:id,name', 'creditNote']) : null;
        $lines = $issued ? $issued->lines->map->toArray()->all() : ($distributor ? $this->invoices->lines($distributor, $customer, $period) : []);

        return view('billing::index', [
            'period' => $period,
            'distributors' => $distributors,
            'customers' => $this->customers->boughtIn($period, $distributors->pluck('id')->all())->groupBy('distributor_id'),
            'to' => (string) $request->query('to'),
            'distributor' => $distributor,
            'customer' => $customer,
            'issued' => $issued,
            'lines' => $lines,
            'totals' => $issued ? $issued->only(['units', 'bonus', 'value', 'sales_tax', 'withholding_tax', 'total']) : $this->invoices->totals($lines),
            'month' => $this->invoices->forMonth($period, $allowed),
            'canIssue' => $request->user()->hasRole('accounts'),
            'canCancel' => $request->user()->hasRole('owner'),
        ]);
    }

    public function store(IssueInvoiceRequest $request, IssueInvoice $issue): RedirectResponse
    {
        [$distributor, $customer] = $request->billTo();

        try {
            $invoice = $issue->handle($distributor, $customer, $request->period(), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', "Invoice {$invoice->number} is issued and locked. Corrections now go through a credit note.");
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice, CancelInvoice $cancel): RedirectResponse
    {
        try {
            $note = $cancel->handle($invoice, $request->validated('reason'), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', "{$invoice->number} is cancelled. Credit note {$note->number} reverses it; you can now issue a new invoice.");
    }

    /** A clean page to print or save as PDF. */
    public function print(Request $request, Invoice $invoice): View
    {
        abort_unless($request->user()->canSeeDistributor($invoice->distributor_id), 403);

        return view('billing::print', ['invoice' => $invoice->load(['lines', 'distributor', 'customer', 'issuedBy:id,name', 'creditNoteFor:id,number'])]);
    }

    /** "d:5" is a distributor account, "c:42" a single customer; only among the distributors shown. */
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
}
