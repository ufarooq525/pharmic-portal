<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Sales\Actions\PostStockReceipt;
use App\Modules\Sales\Actions\ReadStockFile;
use App\Modules\Sales\Actions\RevertStockReceipt;
use App\Modules\Sales\Http\Requests\StoreStockReceiptRequest;
use App\Modules\Sales\Models\StockReceipt;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stock coming in: a delivery entered by hand or uploaded as a CSV, and reverting one. */
class StockReceiptController
{
    public function store(StoreStockReceiptRequest $request, ReadStockFile $readFile, PostStockReceipt $post): RedirectResponse
    {
        try {
            $lines = $request->hasFile('file') ? $readFile->handle($request->file('file')) : $request->typedLines();
            $receipt = $post->handle($request->receiptDetails(), $lines, $request->user(), $request->file('file'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['receipt' => $e->getMessage()]);
        }

        return redirect()->route('inventory.index', ['period' => $receipt->received_on->format('Y-m')])
            ->with('status', 'Stock received: '.fmt_num($receipt->total_units).' units across '.$receipt->line_count.' '.str('product')->plural($receipt->line_count).'. You can revert this receipt below if it was wrong.');
    }

    public function revert(Request $request, StockReceipt $receipt, RevertStockReceipt $revert): RedirectResponse
    {
        try {
            $revert->handle($receipt, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('status', 'Receipt #'.$receipt->id.' reverted. Its '.fmt_num($receipt->total_units).' units are out of stock again.');
    }

    /** A CSV to fill in: one row per product, units and cost left blank. */
    public function template(ProductRepository $products): StreamedResponse
    {
        return response()->streamDownload(function () use ($products) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['product', 'product_name', 'units', 'unit_cost']);
            foreach ($products->active(['code', 'name']) as $p) {
                fputcsv($out, [$p->code, $p->name, '', '']);
            }
            fclose($out);
        }, 'stock-receipt-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
