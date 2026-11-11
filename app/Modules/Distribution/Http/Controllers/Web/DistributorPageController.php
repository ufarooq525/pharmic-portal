<?php

namespace App\Modules\Distribution\Http\Controllers\Web;

use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\ProductAlias;
use App\Modules\Imports\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Each distributor's file profile: the report layout its software prints, the header name, where the
 * bonus comes from, and how it spells and codes each product. Imports are checked against this.
 */
class DistributorPageController
{
    public function index(Request $request): View
    {
        $distributors = Distributor::withCount(['aliases', 'customers', 'imports'])->orderBy('name')->get();
        $current = $distributors->firstWhere('id', $request->integer('distributor')) ?? $distributors->first();

        return view('distribution::distributors', [
            'distributors' => $distributors,
            'current' => $current,
            'aliases' => $current
                ? ProductAlias::where('distributor_id', $current->id)->with('product:id,name')->orderBy('name_in_file')->get()
                : collect(),
            'lastImport' => $current
                ? Import::where('distributor_id', $current->id)->latest('period')->first(['id', 'period', 'status', 'layout', 'distributor_in_file', 'original_name'])
                : null,
            'canEdit' => $request->user()->hasRole('owner'),
        ]);
    }

    public function update(Request $request, Distributor $distributor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'header_name' => ['nullable', 'string', 'max:255'],
            'layout' => ['nullable', Rule::in(array_keys(Distributor::LAYOUTS))],
            'bonus_source' => ['required', Rule::in(array_keys(Distributor::BONUS_SOURCES))],
            'format_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $distributor->update($data);

        return back()->with('status', "{$distributor->name}’s file profile is saved. New uploads are checked against it.");
    }

    public function unlink(Distributor $distributor, ProductAlias $alias): RedirectResponse
    {
        abort_unless($alias->distributor_id === $distributor->id, 404);
        $alias->delete();

        return back()->with('status', "“{$alias->name_in_file}” is no longer linked. The next file that uses it will ask you to link it again.");
    }
}
