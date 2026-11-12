<?php

namespace App\Modules\Distribution\Http\Controllers\Web;

use App\Modules\Distribution\Actions\SaveMedicalRep;
use App\Modules\Distribution\Http\Requests\SaveMedicalRepRequest;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use App\Modules\Distribution\Repositories\ProductAliasRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Master data tabs that live in this module: medical reps and product-name matching. */
class MasterDataController
{
    public function __construct(private readonly MedicalRepRepository $reps) {}

    public function reps(Request $request): View
    {
        return view('distribution::reps', [
            'reps' => $this->reps->withTerritory(),
            'territories' => $this->reps->territories(),
            'linked' => $this->reps->customerCounts(),
            'canEdit' => $request->user()->hasRole('accounts'),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function storeRep(SaveMedicalRepRequest $request, SaveMedicalRep $save): RedirectResponse
    {
        $rep = $save->handle($request->validated());

        return redirect()->route('reps.index')->with('status', "{$rep->name} added to {$rep->territory->name}.");
    }

    public function updateRep(SaveMedicalRepRequest $request, MedicalRep $rep, SaveMedicalRep $save): RedirectResponse
    {
        $save->handle($request->validated() + ['is_active' => $request->boolean('is_active')], $rep);

        return redirect()->route('reps.index')->with('status', "{$rep->name} updated.");
    }

    public function aliases(Request $request, ProductAliasRepository $aliases, DistributorRepository $distributors): View
    {
        $distributorId = $request->integer('distributor') ?: null;

        return view('distribution::aliases', [
            'distributors' => $distributors->visible(null, ['id', 'name']),
            'distributorId' => $distributorId,
            'aliases' => $aliases->list($distributorId),
            'canEdit' => $request->user()->hasRole('owner'),
        ]);
    }
}
