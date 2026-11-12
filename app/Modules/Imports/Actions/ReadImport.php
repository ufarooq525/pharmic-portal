<?php

namespace App\Modules\Imports\Actions;

use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Enums\ReportLayout;
use App\Models\User;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Parsers\ParsedFile;
use App\Modules\Imports\Parsers\PdfImportReader;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Reads an uploaded distributor file and holds it for review. Nothing posts until it is confirmed. */
class ReadImport extends Action
{
    public function __construct(private PdfImportReader $reader) {}

    public function handle(Distributor $distributor, CarbonImmutable $period, UploadedFile $file, User $user): Import
    {
        $path = $file->store('imports/'.$period->format('Y-m'));
        $parsed = $this->reader->read(Storage::path($path));
        $checks = $this->checks($parsed, $distributor, $period);
        $aliases = $distributor->aliases()->get(['name_in_file', 'code_in_file', 'product_id']);
        $byName = $aliases->pluck('product_id', 'name_in_file');
        $byCode = $aliases->whereNotNull('code_in_file')->pluck('product_id', 'code_in_file');

        $import = DB::transaction(function () use ($distributor, $period, $file, $user, $path, $parsed, $checks, $byName, $byCode) {
            // A new upload replaces an earlier one that was never confirmed.
            Import::where('distributor_id', $distributor->id)->whereDate('period', $period)->where('status', '!=', ImportStatus::Confirmed)->get()->each->delete();

            $import = Import::create([
                'distributor_id' => $distributor->id,
                'period' => $period,
                'status' => collect($checks)->every('ok') ? ImportStatus::NeedsReview : ImportStatus::Blocked,
                'layout' => $parsed->layout,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'distributor_in_file' => $parsed->distributorInFile,
                'period_from' => $parsed->periodFrom,
                'period_to' => $parsed->periodTo,
                'file_units' => $parsed->fileUnits,
                'file_value' => $parsed->fileValue,
                'parsed_units' => $parsed->parsedUnits(),
                'parsed_value' => $parsed->parsedValue(),
                'checks' => $checks,
                'customers' => $parsed->customers,
                'uploaded_by' => $user->id,
            ]);

            $import->rows()->insert(array_map(fn (array $line) => [
                'import_id' => $import->id,
                'customer_code' => $line['customer_code'],
                'customer_name' => $line['customer'],
                'area' => $line['area'],
                'product_code' => $line['product_code'] ?? null,
                'product_name' => $line['product'],
                // A spelling already linked wins; the distributor's item code catches a re-spelled name.
                'product_id' => $byName[$line['product']] ?? (isset($line['product_code']) ? $byCode[$line['product_code']] ?? null : null),
                'units' => $line['units'],
                'bonus' => $line['bonus'],
                'trade_price' => $line['trade_price'],
                'value' => $line['value'],
            ], $parsed->lines));

            return $import;
        });

        $failed = collect($checks)->contains(fn ($c) => ! $c['ok']);
        $this->log('Uploaded file', "{$distributor->name}, {$period->format('F Y')}: {$file->getClientOriginalName()}".($failed ? ' (checks failed)' : ''));

        return $import;
    }

    /** @return list<array{key: string, ok: bool, message: string}> */
    private function checks(ParsedFile $parsed, Distributor $distributor, CarbonImmutable $period): array
    {
        $month = $period->format('Y-m');
        $from = $parsed->periodFrom;
        $to = $parsed->periodTo ?? $from;
        $sameMonth = $from !== null && str_starts_with($from, $month) && str_starts_with((string) $to, $month);
        $sameName = $distributor->headerMatches($parsed->distributorInFile);
        $sameLayout = $distributor->layout === null || $distributor->layout->value === $parsed->layout;
        $layout = ReportLayout::tryFrom($parsed->layout)?->label() ?? $parsed->layout;

        return [
            ['key' => 'totals', 'ok' => $parsed->totalsMatch(), 'message' => $parsed->totalsMatch()
                ? 'Units and value match the grand total printed in the file.'
                : "Parsed {$parsed->parsedUnits()} units but the file's grand total says {$parsed->fileUnits}."],
            ['key' => 'period', 'ok' => $sameMonth, 'message' => match (true) {
                $sameMonth => 'The file covers '.$period->format('F Y').' only.',
                $from === null => 'The file does not print the dates it covers.',
                str_starts_with($from, $month) => 'The file runs from '.self::day($from).' to '.self::day($to).'. Export one month only, so other months are not counted twice.',
                default => 'The file covers '.self::day($from).' to '.self::day($to).', not '.$period->format('F Y').'.',
            }],
            ['key' => 'distributor', 'ok' => $sameName, 'message' => $sameName
                ? 'The name at the top of the file matches '.$distributor->name.'.'
                : "The file header reads “{$parsed->distributorInFile}”, not {$distributor->name}."],
            ['key' => 'format', 'ok' => $sameLayout, 'message' => match (true) {
                $distributor->layout === null => "First file from {$distributor->name}: {$layout}. This becomes its saved format when confirmed.",
                $sameLayout => 'Same report format as '.possessive($distributor->name).' earlier files.',
                default => "{$distributor->name} normally sends ".$distributor->layout->label()
                    .", but this file is {$layout}. Check it is the right report, or update the format on the Distributors page.",
            }],
        ];
    }

    private static function day(?string $date): string
    {
        return $date ? CarbonImmutable::parse($date)->format('j M Y') : '?';
    }
}
