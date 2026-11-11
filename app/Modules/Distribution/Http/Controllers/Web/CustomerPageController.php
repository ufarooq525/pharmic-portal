<?php

namespace App\Modules\Distribution\Http\Controllers\Web;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer register is built from the monthly files. A customer missing from a month is never
 * deleted: it shows as "no purchase", and after three empty months as inactive.
 */
class CustomerPageController
{
    public const STATUSES = [
        'all' => 'All',
        'active' => 'Bought this month',
        'new' => 'New this month',
        'needs_rep' => 'Needs a rep',
        'no_purchase' => 'No purchase (1–2 months)',
        'inactive' => 'Inactive (3+ months)',
    ];

    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);
        $status = array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : 'all';
        $distributorId = $request->integer('distributor') ?: null;
        $search = trim((string) $request->query('q'));

        $base = Customer::query()
            ->when($distributorId, fn (Builder $q) => $q->where('distributor_id', $distributorId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$search}%")->orWhere('area', 'like', "%{$search}%")->orWhere('code', $search)));

        $counts = collect(self::STATUSES)->map(fn ($label, $key) => $this->filter(clone $base, $key, $period)->count());

        $customers = $this->filter($base, $status, $period)
            ->with(['distributor:id,name', 'medicalReps:id,name,territory_id', 'medicalReps.territory:id,name'])
            ->withSum(['saleLines as month_units' => fn ($q) => $q->whereDate('period', $period)], 'units')
            ->withSum(['saleLines as month_value' => fn ($q) => $q->whereDate('period', $period)], 'value_tp')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('distribution::customers', [
            'period' => $period,
            'customers' => $customers,
            'status' => $status,
            'statuses' => self::STATUSES,
            'counts' => $counts,
            'distributors' => Distributor::orderBy('name')->get(['id', 'name']),
            'reps' => MedicalRep::where('is_active', true)->with('territory:id,name')->orderBy('name')->get(['id', 'name', 'territory_id']),
            'distributorId' => $distributorId,
            'search' => $search,
            'canAssign' => $request->user()->hasRole('accounts'),
        ]);
    }

    /** One rep gets 100%; two reps share 50/50 (e.g. a store covered from Mardan 1 and Mardan 2). */
    public function assignReps(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'rep_1' => ['nullable', 'exists:medical_reps,id'],
            'rep_2' => ['nullable', 'exists:medical_reps,id', 'different:rep_1'],
        ]);

        $reps = array_values(array_filter([$data['rep_1'] ?? null, $data['rep_2'] ?? null]));
        $share = count($reps) === 2 ? 50 : 100;

        $customer->medicalReps()->sync(collect($reps)->mapWithKeys(fn ($id) => [$id => ['share_percent' => $share]]));

        return back()->with('status', $reps ? "{$customer->name} is now linked to its rep." : "{$customer->name} no longer has a rep.");
    }

    private function filter(Builder $query, string $status, CarbonImmutable $period): Builder
    {
        return match ($status) {
            'active' => $query->whereDate('last_seen', '>=', $period),
            'new' => $query->whereDate('first_seen', $period),
            'needs_rep' => $query->doesntHave('medicalReps'),
            'no_purchase' => $query->whereDate('last_seen', '<', $period)->whereDate('last_seen', '>=', $period->subMonths(2)),
            'inactive' => $query->whereDate('last_seen', '<', $period->subMonths(2)),
            default => $query,
        };
    }
}
