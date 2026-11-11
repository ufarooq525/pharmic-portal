<?php

namespace App\Modules\Imports\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
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
use Illuminate\Http\JsonResponse;
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
            'distributors' => Distributor::orderBy('name')->when($request->user()->visibleDistributorIds(), fn ($q, $ids) => $q->whereIn('id', $ids))->get(),
            'imports' => $imports,
            'selected' => $request->integer('distributor') ?: null,
        ]);
    }

    public function store(StoreImportRequest $request, ReadImport $read): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->canSeeDistributor($request->integer('distributor_id')), 403);

        try {
            $import = $read->handle(
                Distributor::findOrFail($request->integer('distributor_id')),
                CarbonImmutable::createFromFormat('!Y-m', $request->string('period')->toString()),
                $request->file('file'),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422)
                : back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        $failed = collect($import->checks)->reject(fn ($c) => $c['ok']);
        ActivityLog::record('Uploaded file', "{$import->distributor->name}, {$import->period->format('F Y')}: {$import->original_name}".($failed->isEmpty() ? '' : ' (checks failed)'));
        [$flashKey, $status] = $failed->isEmpty()
            ? ['status', 'File read. Check the customers below, then confirm.']
            : ['warning', 'File read, but '.$failed->count().' '.str('check')->plural($failed->count()).' failed. It may be the wrong file.'];

        if ($request->expectsJson()) {
            // The upload screen ticks these off one by one, like the approved prototype, then opens the review.
            $checks = collect($import->checks)->keyBy('key');
            session()->flash($flashKey, $status);

            return response()->json([
                'redirect' => route('imports.show', $import),
                'steps' => [
                    ['label' => 'Recognising the layout', 'result' => Distributor::LAYOUTS[$import->layout] ?? $import->layout, 'ok' => $checks['format']['ok'] ?? true],
                    ['label' => 'Reading the header', 'result' => '“'.$import->distributor_in_file.'” · '.($import->period_from?->format('j M Y') ?? 'no dates').' to '.($import->period_to?->format('j M Y') ?? '?'), 'ok' => ($checks['distributor']['ok'] ?? true) && ($checks['period']['ok'] ?? true)],
                    ['label' => 'Reading customers and lines', 'result' => count($import->customers).' customers · '.$import->rows()->count().' product lines', 'ok' => true],
                    ['label' => 'Checking against the grand total', 'result' => fmt_num($import->parsed_units).' units read · file says '.fmt_num($import->file_units), 'ok' => $checks['totals']['ok'] ?? true],
                ],
            ]);
        }

        return redirect()->route('imports.show', $import)->with($flashKey, $status);
    }

    /** Throw away a file that was never confirmed: wrong distributor, wrong month or a bad export. */
    public function discard(Request $request, Import $import): RedirectResponse
    {
        $this->guard($request, $import);
        abort_if($import->isConfirmed(), 422, 'A confirmed import has to be undone by the owner first.');

        $period = $import->period->format('Y-m');
        $distributorId = $import->distributor_id;
        Storage::delete($import->file_path);
        $import->delete();
        ActivityLog::record('Discarded file', "{$import->distributor->name}, {$import->period->format('F Y')}: {$import->original_name}");

        return redirect()->route('imports.index', ['period' => $period, 'distributor' => $distributorId])
            ->with('status', 'The file was discarded. Nothing was posted. Upload the right file below.');
    }

    public function show(Request $request, Import $import, ImportPreview $preview): View
    {
        $this->guard($request, $import);
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
            'canDiscard' => $request->user()->hasRole('accounts', 'data_entry') && ! $import->isConfirmed(),
        ]);
    }

    /** Link how this distributor spells a product to the product, once. Later files use it automatically. */
    public function map(Request $request, Import $import): RedirectResponse
    {
        $this->guard($request, $import);
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
        ActivityLog::record('Linked product name', "{$import->distributor->name}: “{$data['product_name']}” → ".Product::find($data['product_id'])->name);

        return back()->with('status', "“{$data['product_name']}” is now linked. It will be recognised in every later file from this distributor.");
    }

    public function confirm(Request $request, Import $import, ConfirmImport $confirm): RedirectResponse
    {
        $this->guard($request, $import);
        try {
            $confirm->handle($import, $request->user());
            ActivityLog::record('Confirmed import', "{$import->distributor->name}, {$import->period->format('F Y')}");
        } catch (DomainException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        return redirect()->route('imports.index', ['period' => $import->period->format('Y-m')])
            ->with('status', "{$import->distributor->name} for {$import->period->format('F Y')} is confirmed. Stock, sales and reports are updated.");
    }

    /** Revert a confirmed import. "Remove the file too" also discards it, as if it was never uploaded. */
    public function reverse(Request $request, Import $import, ConfirmImport $confirm): RedirectResponse
    {
        $this->guard($request, $import);
        abort_unless($import->isConfirmed(), 422, 'Only a confirmed import can be reverted.');
        $alsoRemove = $request->boolean('remove_file');

        try {
            $done = $confirm->reverse($import, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        ActivityLog::record('Reverted import', "{$import->distributor->name}, {$import->period->format('F Y')}: ".fmt_num($done['units']).' units back in stock'
            .($done['invoices'] ? ', cancelled '.implode(', ', $done['invoices']) : '').($alsoRemove ? ', file removed' : ''));

        $message = 'Import reverted: '.fmt_num($done['lines']).' sale lines removed and '.fmt_num($done['units']).' units are back in stock'
            .($done['customers'] ? ", {$done['customers']} new ".str('customer')->plural($done['customers']).' taken out of the register' : '')
            .($done['invoices'] ? '. Cancelled with a credit note: '.implode(', ', $done['invoices']) : '').'.';

        if ($alsoRemove) {
            Storage::delete($import->file_path);
            $import->delete();

            return redirect()->route('imports.index', ['period' => $import->period->format('Y-m'), 'distributor' => $import->distributor_id])
                ->with('status', $message.' The file is removed; upload the right one below.');
        }

        return redirect()->route('imports.show', $import)->with('status', $message.' The file is back in review: fix it and confirm again, or discard it.');
    }

    /** The original PDF, for checking a figure against the source. */
    public function file(Request $request, Import $import): StreamedResponse
    {
        $this->guard($request, $import);
        return Storage::download($import->file_path, $import->original_name);
    }

    /** A user limited to some distributors cannot open another distributor's file. */
    private function guard(Request $request, Import $import): void
    {
        abort_unless($request->user()->canSeeDistributor($import->distributor_id), 403);
    }
}
