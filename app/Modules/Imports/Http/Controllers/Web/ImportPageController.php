<?php

namespace App\Modules\Imports\Http\Controllers\Web;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\ProductAlias;
use App\Modules\Imports\Http\Requests\StoreImportRequest;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Services\ConfirmImport;
use App\Modules\Imports\Services\ImportPreview;
use App\Modules\Imports\Services\ReadImport;
use App\Support\Period;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportPageController
{
    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);
        $imports = Import::whereDate('period', $period)->latest('id')->get()->unique('distributor_id')->keyBy('distributor_id');

        return view('imports::index', [
            'period' => $period,
            'distributors' => Distributor::orderBy('name')->get(),
            'imports' => $imports,
            'selected' => $request->integer('distributor') ?: null,
        ]);
    }

    public function store(StoreImportRequest $request, ReadImport $read): RedirectResponse
    {
        try {
            $import = $read->handle(
                Distributor::findOrFail($request->integer('distributor_id')),
                CarbonImmutable::createFromFormat('!Y-m', $request->string('period')->toString()),
                $request->file('file'),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('imports.show', $import)->with('status', 'File read. Check the customers below, then confirm.');
    }

    public function show(Request $request, Import $import, ImportPreview $preview): View
    {
        $import->load('distributor');
        $data = $preview->build($import);
        $attentionOnly = $request->query('show') === 'attention';

        return view('imports::review', $data + [
            'import' => $import,
            'period' => CarbonImmutable::parse($import->period),
            'attentionOnly' => $attentionOnly,
            'shown' => $attentionOnly ? array_values(array_filter($data['customers'], fn ($c) => $c['attention'])) : $data['customers'],
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canMap' => $request->user()->hasRole('accounts', 'data_entry') && ! $import->isConfirmed(),
            'canConfirm' => $request->user()->hasRole('accounts') && $import->status === Import::NEEDS_REVIEW && $data['unmapped'] === [],
            'canReverse' => $request->user()->hasRole() && $import->isConfirmed(),
        ]);
    }

    /** Link how this distributor spells a product to the product, once. Later files use it automatically. */
    public function map(Request $request, Import $import): RedirectResponse
    {
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'exists:products,id'],
        ]);

        DB::transaction(function () use ($import, $data) {
            // Keep the distributor's item code with the link when the file prints exactly one for this name,
            // so a later file that re-spells the product is still recognised.
            $codes = $import->rows()->where('product_name', $data['product_name'])->whereNotNull('product_code')->distinct()->pluck('product_code');

            ProductAlias::updateOrCreate(
                ['distributor_id' => $import->distributor_id, 'name_in_file' => $data['product_name']],
                ['product_id' => $data['product_id'], 'code_in_file' => $codes->count() === 1 ? $codes->first() : null],
            );
            $import->rows()->where('product_name', $data['product_name'])->update(['product_id' => $data['product_id']]);
        });

        return back()->with('status', "“{$data['product_name']}” is now linked. It will be recognised in every later file from this distributor.");
    }

    public function confirm(Request $request, Import $import, ConfirmImport $confirm): RedirectResponse
    {
        try {
            $confirm->handle($import, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        return redirect()->route('imports.index', ['period' => $import->period->format('Y-m')])
            ->with('status', "{$import->distributor->name} for {$import->period->format('F Y')} is confirmed. Stock, sales and reports are updated.");
    }

    public function reverse(Import $import, ConfirmImport $confirm): RedirectResponse
    {
        abort_unless($import->isConfirmed(), 422, 'Only a confirmed import can be undone.');

        DB::transaction(fn () => $confirm->reverse($import));

        return back()->with('status', 'Import undone. Its sales and stock movements were removed; it is back in review.');
    }

    /** The original PDF, for checking a figure against the source. */
    public function file(Import $import): StreamedResponse
    {
        return Storage::download($import->file_path, $import->original_name);
    }
}
