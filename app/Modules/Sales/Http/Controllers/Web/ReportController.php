<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use App\Modules\Sales\Enums\ReportType;
use App\Modules\Sales\Http\Requests\ReportRequest;
use App\Modules\Sales\Repositories\ReportRepository;
use App\Support\Http\CsvDownload;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController
{
    private const PER_PAGE = 50;

    public function __construct(private readonly ReportRepository $reports) {}

    public function show(ReportRequest $request, DistributorRepository $distributors, ProductRepository $products, MedicalRepRepository $reps, string $type = 'sales'): View
    {
        $report = ReportType::tryFrom($type) ?? abort(404);
        $period = $request->period();
        $data = $this->reports->build($report, $period, $request->filters());

        // Totals cover every row; the screen shows 50 rows a page. The CSV export has them all.
        $page = LengthAwarePaginator::resolveCurrentPage();
        $data['rows'] = new LengthAwarePaginator(array_slice($data['rows'], ($page - 1) * self::PER_PAGE, self::PER_PAGE), count($data['rows']), self::PER_PAGE, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('sales::report', $data + [
            'type' => $report->value,
            'titles' => ReportType::options(),
            'period' => $period,
            'filters' => $request->filters(),
            'distributors' => $distributors->visible($request->user()->visibleDistributorIds(), ['id', 'name']),
            'products' => $products->all(['id', 'name'], orderBy: 'name'),
            'reps' => $reps->all(['id', 'name'], orderBy: 'name'),
        ]);
    }

    /** Same report as a CSV that opens straight in Excel. */
    public function export(ReportRequest $request, string $type): StreamedResponse
    {
        $report = ReportType::tryFrom($type) ?? abort(404);
        $period = $request->period();
        $data = $this->reports->build($report, $period, $request->filters());
        $keys = array_keys($data['columns']);

        $rows = array_map(fn ($row) => array_map(fn ($key) => $row[$key] ?? '', $keys), $data['rows']);
        $rows[] = array_map(fn ($key) => $key === $keys[0] ? 'Total' : ($data['totals'][$key] ?? ''), $keys);

        return CsvDownload::make("pharmic-{$report->value}-{$period->format('Y-m')}.csv", array_column($data['columns'], 0), $rows);
    }
}
