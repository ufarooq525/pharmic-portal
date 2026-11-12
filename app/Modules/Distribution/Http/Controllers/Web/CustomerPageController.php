<?php

namespace App\Modules\Distribution\Http\Controllers\Web;

use App\Modules\Distribution\Actions\AssignCustomerReps;
use App\Modules\Distribution\Enums\CustomerFilter;
use App\Modules\Distribution\Http\Requests\AssignRepsRequest;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Repositories\CustomerRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer register. Customers come from the distributors' monthly files and are never
 * deleted: one that stops buying shows as "no purchase", and after three empty months as inactive.
 */
class CustomerPageController
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly DistributorRepository $distributors,
        private readonly MedicalRepRepository $reps,
    ) {}

    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);
        $filter = CustomerFilter::tryFrom((string) $request->query('status')) ?? CustomerFilter::All;
        $distributorId = $request->integer('distributor') ?: null;
        $search = trim((string) $request->query('q'));
        $allowed = $request->user()->visibleDistributorIds();
        $base = $this->customers->base($allowed, $distributorId, $search);

        return view('distribution::customers', [
            'period' => $period,
            'customers' => $this->customers->list($base, $filter, $period),
            'status' => $filter->value,
            'statuses' => CustomerFilter::options(),
            'counts' => $this->customers->counts($base, $period),
            'distributors' => $this->distributors->visible($allowed, ['id', 'name']),
            'reps' => $this->reps->withTerritory(activeOnly: true),
            'distributorId' => $distributorId,
            'search' => $search,
            'canAssign' => $request->user()->hasRole('accounts', 'data_entry'),
        ]);
    }

    public function assignReps(AssignRepsRequest $request, Customer $customer, AssignCustomerReps $assign): RedirectResponse
    {
        abort_unless($request->user()->canSeeDistributor($customer->distributor_id), 403);
        $assign->handle($customer, $request->repIds());

        return back()->with('status', $request->repIds() ? "{$customer->name} is now linked to its rep." : "{$customer->name} no longer has a rep.");
    }
}
