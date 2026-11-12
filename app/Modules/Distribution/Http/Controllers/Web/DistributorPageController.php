<?php

namespace App\Modules\Distribution\Http\Controllers\Web;

use App\Modules\Distribution\Actions\UnlinkProductName;
use App\Modules\Distribution\Actions\UpdateDistributorProfile;
use App\Modules\Distribution\Http\Requests\UpdateDistributorRequest;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\ProductAlias;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\ProductAliasRepository;
use App\Modules\Imports\Repositories\ImportRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Each distributor's file profile: layout, header name, bonus source, and its product spellings and codes. */
class DistributorPageController
{
    public function __construct(
        private readonly DistributorRepository $distributors,
        private readonly ProductAliasRepository $aliases,
        private readonly ImportRepository $imports,
    ) {}

    public function index(Request $request): View
    {
        $distributors = $this->distributors->withCounts($request->user()->visibleDistributorIds());
        $current = $distributors->firstWhere('id', $request->integer('distributor')) ?? $distributors->first();

        return view('distribution::distributors', [
            'distributors' => $distributors,
            'current' => $current,
            'aliases' => $current ? $this->aliases->forDistributor($current->id) : collect(),
            'lastImport' => $current ? $this->imports->latestFor($current->id) : null,
            'canEdit' => $request->user()->hasRole('owner'),
        ]);
    }

    public function update(UpdateDistributorRequest $request, Distributor $distributor, UpdateDistributorProfile $update): RedirectResponse
    {
        $update->handle($distributor, $request->validated());

        return back()->with('status', "{$distributor->name}’s file profile is saved. New uploads are checked against it.");
    }

    public function unlink(Distributor $distributor, ProductAlias $alias, UnlinkProductName $unlink): RedirectResponse
    {
        abort_unless($alias->distributor_id === $distributor->id, 404);
        $unlink->handle($alias);

        return back()->with('status', "“{$alias->name_in_file}” is no longer linked. The next file that uses it will ask you to link it again.");
    }
}
