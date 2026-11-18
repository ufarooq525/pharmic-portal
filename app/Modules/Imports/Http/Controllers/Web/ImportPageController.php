<?php

namespace App\Modules\Imports\Http\Controllers\Web;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Imports\Actions\AttachStockReport;
use App\Modules\Imports\Actions\ConfirmImport;
use App\Modules\Imports\Actions\DiscardImport;
use App\Modules\Imports\Actions\LinkProductName;
use App\Modules\Imports\Actions\ReadImport;
use App\Modules\Imports\Actions\RevertImport;
use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Http\Requests\AttachStockReportRequest;
use App\Modules\Imports\Http\Requests\LinkProductNameRequest;
use App\Modules\Imports\Http\Requests\RevertImportRequest;
use App\Modules\Imports\Http\Requests\StoreImportRequest;
use App\Modules\Imports\Http\Resources\ImportReadResource;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Parsers\PdfText;
use App\Modules\Imports\Repositories\ImportRepository;
use App\Modules\Imports\Repositories\ImportReviewRepository;
use App\Support\Period;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportPageController
{
    public function __construct(private readonly ImportRepository $imports) {}

    /** "Import review" in the menu: opens the next file of the month that still needs someone. */
    public function review(Request $request): RedirectResponse
    {
        $period = Period::fromRequest($request);
        $next = $this->imports->nextForReview($period, $request->user()->visibleDistributorIds());

        return $next
            ? redirect()->route('imports.show', ['import' => $next, 'period' => $period->format('Y-m')])
            : redirect()->route('imports.index', ['period' => $period->format('Y-m')])->with('status', 'Nothing to review for '.$period->format('F Y').'. Every uploaded file is confirmed.');
    }

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
            // 50 customers a page: a file can list hundreds. The checks and totals above still cover the whole file.
            'shown' => \App\Support\Paginate::collection($attentionOnly ? array_values(array_filter($data['customers'], fn ($c) => $c['attention'])) : $data['customers'], 50, 'customers_page', 'customers'),
            'products' => $products->active(['id', 'name']),
            'canMap' => $user->can('imports.upload') && ! $import->isConfirmed(),
            'canConfirm' => $user->can('imports.confirm') && $import->status === ImportStatus::NeedsReview && $data['unmapped'] === [],
            'canReverse' => $user->can('imports.revert') && $import->isConfirmed(),
            'canDiscard' => $user->can('imports.upload') && ! $import->isConfirmed(),
        ]);
    }

    public function map(LinkProductNameRequest $request, Import $import, LinkProductName $link): RedirectResponse
    {
        $this->guard($request, $import);
        $link->handle($import, $request->validated('product_name'), $request->product());

        return back()->with('status', "“{$request->validated('product_name')}” is now linked. It will be recognised in every later file from this distributor.");
    }

    /** The distributor's Sales & Stock report for the same month: the bonus per product, shared out across customers. */
    public function stockReport(AttachStockReportRequest $request, Import $import, AttachStockReport $attach): RedirectResponse
    {
        $this->guard($request, $import);

        try {
            $attach->handle($import, $request->file('stock_report'));
        } catch (RuntimeException|DomainException $e) {
            return back()->withErrors(['stock_report' => $e->getMessage()]);
        }

        return back()->with('status', 'Sales & Stock report read. Each product’s bonus is now shared out across its customers; check the products table.');
    }

    public function stockReportFile(Request $request, Import $import): StreamedResponse
    {
        $this->guard($request, $import);
        abort_unless($import->stock_report_path && Storage::exists($import->stock_report_path), 404);

        return Storage::download($import->stock_report_path, $import->stock_report_name);
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

    /**
     * One customer of the file, for the review drawer: its lines as read, and the PDF opened at the
     * page that customer is on, so the reviewer can match them side by side.
     */
    public function customer(Request $request, Import $import, int $index, ImportReviewRepository $review): View
    {
        $this->guard($request, $import);
        $import->load('distributor');
        $customer = collect($review->build($import)['customers'])->firstWhere('index', $index);
        abort_unless($customer, 404);

        return view('imports::customer', [
            'import' => $import,
            'customer' => $customer,
            // The page its lines were read from; files read before pages were recorded fall back to a text search.
            'page' => collect($customer['lines'])->pluck('source_page')->filter()->min() ?? $this->pageOf($import, $customer),
            'hasFile' => Storage::exists($import->file_path),
        ]);
    }

    /** The PDF shown inside the page (the drawer); only this site may frame it. */
    public function view(Request $request, Import $import): \Symfony\Component\HttpFoundation\Response
    {
        $this->guard($request, $import);
        abort_unless(Storage::exists($import->file_path), 404);

        return Storage::response($import->file_path, $import->original_name, [
            'Content-Type' => 'application/pdf',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, max-age=600',
        ], 'inline');
    }

    /**
     * The page a customer appears on (by its code, otherwise its name), or null. The page text is read
     * once per file and kept for a day; a file that can't be read just opens on page 1.
     */
    private function pageOf(Import $import, array $customer): ?int
    {
        $normal = fn (string $s) => preg_replace('/\s+/', ' ', mb_strtolower($s));
        $pages = Cache::remember("import:{$import->id}:pages", now()->addDay(), function () use ($import, $normal) {
            try {
                return Storage::exists($import->file_path) ? array_map($normal, PdfText::pages(Storage::path($import->file_path))) : [];
            } catch (RuntimeException) {
                return [];
            }
        });

        foreach (array_filter([$customer['code'] ?? null, $customer['name'] ?? null]) as $needle) {
            $needle = $normal((string) $needle);
            foreach ($pages as $i => $text) {
                if ($needle !== '' && preg_match('/(?<![\p{L}\d])'.preg_quote($needle, '/').'(?![\p{L}\d])/u', $text)) {
                    return $i + 1;
                }
            }
        }

        return null;
    }

    /** A user limited to some distributors cannot open another distributor's file. */
    private function guard(Request $request, Import $import): void
    {
        abort_unless($request->user()->canSeeDistributor($import->distributor_id), 403);
    }
}
