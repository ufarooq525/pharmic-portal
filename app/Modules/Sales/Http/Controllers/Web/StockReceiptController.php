<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\StockReceipt;
use App\Modules\Sales\Services\StockReceiptService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stock coming in: a delivery entered by hand or uploaded as a CSV, and reverting one. */
class StockReceiptController
{
    public function store(Request $request, StockReceiptService $receipts): RedirectResponse
    {
        $data = $request->validate([
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
            'lines' => ['nullable', 'array', 'max:50'],
            'lines.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.units' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $lines = $request->hasFile('file')
                ? $receipts->readCsv($request->file('file'))
                : collect($data['lines'] ?? [])->filter(fn ($l) => ! empty($l['product_id']) && ! empty($l['units']))
                    ->map(fn ($l) => ['product_id' => (int) $l['product_id'], 'units' => (float) $l['units'], 'unit_cost' => isset($l['unit_cost']) && $l['unit_cost'] !== '' ? (float) $l['unit_cost'] : null])
                    ->values()->all();

            $receipt = $receipts->post(
                collect($data)->only(['received_on', 'reference', 'supplier', 'note'])->all(),
                $lines,
                $request->user(),
                $request->file('file'),
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['receipt' => $e->getMessage()]);
        }

        $units = $receipt->total_units;
        ActivityLog::record('Received stock', 'Receipt #'.$receipt->id.($receipt->reference ? " ({$receipt->reference})" : '').': '.count($lines).' products, '.fmt_num($units).' units'.($receipt->original_name ? " from {$receipt->original_name}" : ''));

        return redirect()->route('inventory.index', ['period' => $receipt->received_on->format('Y-m')])
            ->with('status', 'Stock received: '.fmt_num($units).' units across '.count($lines).' '.str('product')->plural(count($lines)).'. You can revert this receipt below if it was wrong.');
    }

    public function revert(Request $request, StockReceipt $receipt, StockReceiptService $receipts): RedirectResponse
    {
        try {
            $units = $receipt->total_units;
            $receipts->revert($receipt, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        ActivityLog::record('Reverted stock receipt', 'Receipt #'.$receipt->id.($receipt->reference ? " ({$receipt->reference})" : '').': '.fmt_num($units).' units taken back out');

        return back()->with('status', 'Receipt #'.$receipt->id.' reverted. Its '.fmt_num($units).' units are out of stock again.');
    }

    /** A CSV to fill in: one row per product, units and cost left blank. */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['product', 'product_name', 'units', 'unit_cost']);
            foreach (Product::where('is_active', true)->orderBy('name')->get(['code', 'name']) as $p) {
                fputcsv($out, [$p->code, $p->name, '', '']);
            }
            fclose($out);
        }, 'stock-receipt-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
