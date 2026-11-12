<?php

namespace App\Modules\Distribution\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Distribution\Models\ProductAlias;
use App\Modules\Distribution\Models\Territory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Master data tabs that live in this module: medical reps and product-name matching. */
class MasterDataController
{
    public function reps(Request $request): View
    {
        $linked = DB::table('customer_medical_rep')->groupBy('medical_rep_id')->selectRaw('medical_rep_id, COUNT(*) AS n')->pluck('n', 'medical_rep_id');

        return view('distribution::reps', [
            'reps' => MedicalRep::with('territory')->orderBy('name')->get(),
            'territories' => Territory::orderBy('name')->get(),
            'linked' => $linked,
            'canEdit' => $request->user()->hasRole('accounts'),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function storeRep(Request $request): RedirectResponse
    {
        $rep = MedicalRep::create($this->repData($request));
        ActivityLog::record('Added medical rep', "{$rep->name}, {$rep->territory->name}");

        return redirect()->route('reps.index')->with('status', "{$rep->name} added to {$rep->territory->name}.");
    }

    public function updateRep(Request $request, MedicalRep $rep): RedirectResponse
    {
        $rep->update($this->repData($request) + ['is_active' => $request->boolean('is_active')]);
        ActivityLog::record('Changed medical rep', "{$rep->name}, {$rep->territory->name}".($rep->is_active ? '' : ', inactive'));

        return redirect()->route('reps.index')->with('status', "{$rep->name} updated.");
    }

    public function aliases(Request $request): View
    {
        $distributorId = $request->integer('distributor') ?: null;

        return view('distribution::aliases', [
            'distributors' => Distributor::orderBy('name')->get(['id', 'name']),
            'distributorId' => $distributorId,
            'aliases' => ProductAlias::with(['distributor:id,name', 'product:id,name'])
                ->when($distributorId, fn ($q) => $q->where('distributor_id', $distributorId))
                ->orderBy(Distributor::select('name')->whereColumn('distributors.id', 'product_aliases.distributor_id'))
                ->orderBy('name_in_file')->paginate(50)->withQueryString(),
            'canEdit' => $request->user()->hasRole('owner'),
        ]);
    }

    /** @return array<string, mixed> */
    private function repData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'territory' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'base_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        return [
            'name' => $data['name'],
            'territory_id' => Territory::firstOrCreate(['name' => trim($data['territory'])])->id,
            'phone' => $data['phone'] ?? null,
            'base_salary' => $data['base_salary'] ?? 0,
        ];
    }
}
