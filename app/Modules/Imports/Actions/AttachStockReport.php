<?php

namespace App\Modules\Imports\Actions;

use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Parsers\StockReportParser;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads the distributor's Sales & Stock report and keeps it with the import of the same month, for
 * sharing each product's bonus out across customers. A new report replaces the earlier one. Its checks
 * are warnings: a report that disagrees with the customer file never blocks the import.
 */
class AttachStockReport extends Action
{
    public function __construct(private readonly StockReportParser $parser) {}

    public function handle(Import $import, UploadedFile $file): Import
    {
        if ($import->isConfirmed()) {
            throw new DomainException('This import is already confirmed. Revert it first to change its Sales & Stock report.');
        }

        $import->loadMissing('distributor');
        $path = $file->store('imports/'.$import->period->format('Y-m').'/stock');

        try {
            $report = $this->parser->read(Storage::path($path));
        } catch (RuntimeException $e) {
            Storage::delete($path);
            throw $e;
        }

        $previous = $import->stock_report_path;
        $import->update([
            'stock_report_name' => $file->getClientOriginalName(),
            'stock_report_path' => $path,
            'stock_report' => ['items' => $report['items'], 'printed' => $report['printed'], 'distributor_in_file' => $report['distributor_in_file'],
                'period_from' => $report['period_from'], 'period_to' => $report['period_to']],
            'stock_report_checks' => $this->checks($import, $report),
        ]);

        if ($previous && $previous !== $path) {
            Storage::delete($previous);
        }

        $this->log('Attached Sales & Stock report', "{$import->distributor->name}, {$import->period->format('F Y')}: {$file->getClientOriginalName()}");

        return $import;
    }

    /** @return list<array{key: string, ok: bool, message: string}> */
    private function checks(Import $import, array $report): array
    {
        $month = $import->period->format('Y-m');
        $from = $report['period_from'];
        $to = $report['period_to'] ?? $from;
        $sameMonth = $from !== null && str_starts_with($from, $month) && str_starts_with((string) $to, $month);
        $sameName = $import->distributor->headerMatches($report['distributor_in_file']);
        $sums = StockReportParser::sums($report['items']);
        $printed = $report['printed'];
        $totalsOk = $printed !== null && $report['unread'] === []
            && collect($sums)->every(fn ($v, $k) => abs($v - $printed[$k]) < 0.01);
        $day = fn (?string $d) => $d ? CarbonImmutable::parse($d)->format('j M Y') : '?';

        return [
            ['key' => 'ssr_totals', 'ok' => $totalsOk, 'message' => match (true) {
                $totalsOk => 'Sales & Stock report: '.count($report['items']).' products read; they add up to its grand total ('.fmt_num($sums['net_units']).' units, '.fmt_num($sums['net_bonus']).' bonus).',
                $report['unread'] !== [] => 'Sales & Stock report: '.count($report['unread']).' product '.str('line')->plural(count($report['unread'])).' could not be read: '.implode(' | ', array_slice($report['unread'], 0, 3)),
                $printed === null => 'Sales & Stock report: its grand total could not be found, so the products read could not be checked.',
                default => 'Sales & Stock report: the products read ('.fmt_num($sums['net_units']).' units, '.fmt_num($sums['net_bonus']).' bonus) do not add up to its grand total ('.fmt_num($printed['net_units']).' units, '.fmt_num($printed['net_bonus']).' bonus).',
            }],
            ['key' => 'ssr_period', 'ok' => $sameMonth, 'message' => $sameMonth
                ? 'Sales & Stock report covers '.$import->period->format('F Y').'.'
                : 'Sales & Stock report covers '.$day($from).' to '.$day($to).', not '.$import->period->format('F Y').'.'],
            ['key' => 'ssr_distributor', 'ok' => $sameName, 'message' => $sameName
                ? 'Sales & Stock report is from '.$import->distributor->name.'.'
                : "Sales & Stock report header reads “{$report['distributor_in_file']}”, not {$import->distributor->name}."],
        ];
    }
}
