<?php

namespace App\Modules\Imports\Console;

use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Parsers\PdfImportReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Fills in, for files read before it was recorded: where each row came from (PDF page and printed line) and
 * each customer's own total as printed in the file. The stored PDF is read again; a row is filled only when the
 * line in the same position has the same product name and units, a customer only when its code (or name and
 * area) matches, and only empty fields are written. Nothing posted is changed.
 *   php artisan imports:trace            (every import missing it)
 *   php artisan imports:trace --import=12
 */
class TraceImportsCommand extends Command
{
    protected $signature = 'imports:trace {--import= : Only this import id}';

    protected $description = 'Record the PDF page, printed line and customer totals for files imported before they were tracked';

    public function handle(PdfImportReader $reader): int
    {
        $imports = Import::query()->when($this->option('import'), fn ($q, $id) => $q->whereKey($id))->orderBy('id')->get()
            ->filter(fn (Import $i) => $i->rows()->whereNull('source_text')->exists() || collect($i->customers)->contains(fn ($c) => ! array_key_exists('file_units', $c)));

        foreach ($imports as $import) {
            $label = "#{$import->id} {$import->original_name}";

            if (! Storage::exists($import->file_path)) {
                $this->warn("$label: the PDF is no longer stored.");

                continue;
            }

            try {
                $parsed = $reader->read(Storage::path($import->file_path));
                $lines = $parsed->lines;
            } catch (Throwable $e) {
                $this->warn("$label: could not be read again ({$e->getMessage()}).");

                continue;
            }

            // Customers' own printed totals.
            $key = fn (array $c) => $c['code'] ?? (($c['name'] ?? '').'|'.($c['area'] ?? ''));
            $printed = collect($parsed->customers)->keyBy($key);
            $totals = 0;
            $customers = array_map(function (array $c) use ($printed, $key, &$totals) {
                $p = $printed->get($key($c));
                if (! array_key_exists('file_units', $c) && $p && array_key_exists('file_units', $p)) {
                    $totals++;

                    return $c + array_intersect_key($p, array_flip(['file_units', 'file_bonus', 'file_value']));
                }

                return $c;
            }, $import->customers);
            if ($totals) {
                $import->update(['customers' => $customers]);
            }

            $rows = $import->rows()->orderBy('id')->get();
            if ($rows->count() !== count($lines)) {
                $this->line("$label: {$totals} customer totals added.");
                $this->warn("$label: {$rows->count()} rows stored but the file now reads as ".count($lines).' lines; left as is.');

                continue;
            }

            $filled = $skipped = 0;
            foreach ($rows as $i => $row) {
                $line = $lines[$i];
                if ($row->source_text !== null) {
                    continue;
                }
                if ($line['product'] !== $row->product_name || abs((float) $line['units'] - (float) $row->units) > 0.001) {
                    $skipped++;

                    continue;
                }
                $row->update(['source_page' => $line['page'] ?? null, 'source_text' => isset($line['source']) ? mb_substr($line['source'], 0, 500) : null]);
                $filled++;
            }

            $this->line("$label: {$filled} rows traced, {$totals} customer totals added".($skipped ? ", {$skipped} rows did not match and were left as is" : '').'.');
        }

        $this->info($imports->isEmpty() ? 'Every import is already traced.' : 'Done.');

        return self::SUCCESS;
    }
}
