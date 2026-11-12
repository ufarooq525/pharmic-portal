<?php

namespace App\Modules\Imports\Http\Controllers\Web;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Imports\Actions\ConfirmImport;
use App\Modules\Imports\Actions\DiscardImport;
use App\Modules\Imports\Actions\LinkProductName;
use App\Modules\Imports\Actions\ReadImport;
use App\Modules\Imports\Actions\RevertImport;
use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Http\Requests\LinkProductNameRequest;
use App\Modules\Imports\Http\Requests\RevertImportRequest;
use App\Modules\Imports\Http\Requests\StoreImportRequest;
use App\Modules\Imports\Http\Resources\ImportReadResource;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Repositories\ImportRepository;
use App\Modules\Imports\Repositories\ImportReviewRepository;
use App\Support\Period;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportPageController
{
    public function __construct(private readonly ImportRepository $imports) {}

    public function index(Request $request, DistributorRepository $distributors): View
    {
        $period = Period::fromRequest($request);

        return view('imports::index', [
            'period' => $period,
            'distributors' => $distributors->visible($request->user()->visibleDistributorIds()),
            'imports' => $this->imports->latestPerDistributor($period),
            'selected' => $request->integer('distributor') ?: null,
        ]);
    }

    public function store(StoreImportRequest $request, ReadImport $read): RedirectResponse|JsonResponse|ImportReadResource
    {
        try {
            $import = $read->handle($request->distributor(), $request->period(), $request->file('file'), $request->user());
        } catch (RuntimeException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422)
                : back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        $failed = collect($import->checks)->reject(fn ($c) => $c['ok'])->count();
        [$flashKey, $message] = $failed === 0
            ? ['status', 'File read. Check the customers below, then confirm.']
            : ['warning', "File read, but {$failed} ".str('check')->plural($failed).' failed. It may be the wrong file.'];

        if ($request->expectsJson()) {
            session()->flash($flashKey, $message);

            return new ImportReadResource($import);
        }

        return redirect()->route('imports.show', $import)->with($flashKey, $message);
    }

    public function show(Request $request, Import $import, ImportReviewRepository $review, ProductRepository $products): View
    {
        $this->guard($request, $import);
        $import->load('distributor');
        $data = $review->build($import);
        $attentionOnly = $request->query('show') === 'attention';
        $user = $request->user();

        return view('imports::review', $data + [
            'import' => $import,
            'period' => CarbonImmutable::parse($import->period),
            'attentionOnly' => $attentionOnly,
            'shown' => $attentionOnly ? array_values(array_filter($data['customers'], fn ($c) => $c['attention'])) : $data['customers'],
            'products' => $products->active(['id', 'name']),
            'canMap' => $user->hasRole('accounts', 'data_entry') && ! $import->isConfirmed(),
            'canConfirm' => $user->hasRole('accounts') && $import->status === ImportStatus::NeedsReview && $data['unmapped'] === [],
            'canReverse' => $user->hasRole() && $import->isConfirmed(),
            'canDiscard' => $user->hasRole('accounts', 'data_entry') && ! $import->isConfirmed(),
        ]);
    }

    public function map(LinkProductNameRequest $request, Import $import, LinkProductName $link): RedirectResponse
    {
        $this->guard($request, $import);
        $link->handle($import, $request->validated('product_name'), $request->product());

        return back()->with('status', "“{$request->validated('product_name')}” is now linked. It will be recognised in every later file from this distributor.");
    }

    public function confirm(Request $request, Import $import, ConfirmImport $confirm): RedirectResponse
    {
        $this->guard($request, $import);

        try {
            $confirm->handle($import, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        return redirect()->route('imports.index', ['period' => $import->period->format('Y-m')])
            ->with('status', "{$import->distributor->name} for {$import->period->format('F Y')} is confirmed. Stock, sales and reports are updated.");
    }

    /** Revert a confirmed import; "Remove the file too" also discards it, as if it was never uploaded. */
    public function reverse(RevertImportRequest $request, Import $import, RevertImport $revert, DiscardImport $discard): RedirectResponse
    {
        $this->guard($request, $import);
        abort_unless($import->isConfirmed(), 422, 'Only a confirmed import can be reverted.');

        try {
            $done = $revert->handle($import->load('distributor'), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        $message = 'Import reverted: '.fmt_num($done['lines']).' sale lines removed and '.fmt_num($done['units']).' units are back in stock'
            .($done['customers'] ? ", {$done['customers']} new ".str('customer')->plural($done['customers']).' taken out of the register' : '')
            .($done['invoices'] ? '. Cancelled with a credit note: '.implode(', ', $done['invoices']) : '').'.';

        if ($request->boolean('remove_file')) {
            $discard->handle($import->refresh());

            return redirect()->route('imports.index', ['period' => $import->period->format('Y-m'), 'distributor' => $import->distributor_id])
                ->with('status', $message.' The file is removed; upload the right one below.');
        }

        return redirect()->route('imports.show', $import)->with('status', $message.' The file is back in review: fix it and confirm again, or discard it.');
    }

    public function discard(Request $request, Import $import, DiscardImport $discard): RedirectResponse
    {
        $this->guard($request, $import);

        try {
            $discard->handle($import);
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()->route('imports.index', ['period' => $import->period->format('Y-m'), 'distributor' => $import->distributor_id])
            ->with('status', 'The file was discarded. Nothing was posted. Upload the right file below.');
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
