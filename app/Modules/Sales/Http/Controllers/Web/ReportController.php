<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Sales\Services\ReportBuilder;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController
{
    public const TYPES = ['sales', 'customers', 'bonus', 'incentive', 'taxes'];

    public function show(Request $request, ReportBuilder $builder, string $type = 'sales'): View
    {
        $period = Period::fromRequest($request);
        $filters = $this->filters($request);

        return view('sales::report', $builder->build($type, $period, $filters) + [
            'type' => $type,
            'titles' => ReportBuilder::TITLES,
            'period' => $period,
            'filters' => $filters,
            'distributors' => Distributor::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'reps' => MedicalRep::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Same report as a CSV that opens straight in Excel (UTF-8 with BOM). */
    public function export(Request $request, ReportBuilder $builder, string $type): StreamedResponse
    {
        $period = Period::fromRequest($request);
        $report = $builder->build($type, $period, $this->filters($request));
        $file = "pharmic-{$type}-{$period->format('Y-m')}.csv";

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($report['columns'], 0));

            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', array_keys($report['columns'])));
            }

            fputcsv($out, array_map(fn ($key) => $key === array_key_first($report['columns']) ? 'Total' : ($report['totals'][$key] ?? ''), array_keys($report['columns'])));
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return [
            'distributor' => $request->integer('distributor') ?: null,
            'product' => $request->integer('product') ?: null,
            'rep' => $request->integer('rep') ?: null,
        ];
    }
}
