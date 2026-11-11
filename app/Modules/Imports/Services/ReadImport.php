<?php

namespace App\Modules\Imports\Services;

use App\Models\User;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Parsers\ParsedFile;
use App\Modules\Imports\Parsers\PdfImportReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Reads an uploaded distributor file and holds it for review. Nothing posts until it is confirmed. */
class ReadImport
{
    public function __construct(private PdfImportReader $reader) {}

    public function handle(Distributor $distributor, CarbonImmutable $period, UploadedFile $file, User $user): Import
    {
        $path = $file->store('imports/'.$period->format('Y-m'));
        $parsed = $this->reader->read(Storage::path($path));
        $checks = $this->checks($parsed, $distributor, $period);
        $aliases = $distributor->aliases()->pluck('product_id', 'name_in_file');

        return DB::transaction(function () use ($distributor, $period, $file, $user, $path, $parsed, $checks, $aliases) {
            // A new upload replaces an earlier one that was never confirmed.
            Import::where('distributor_id', $distributor->id)->whereDate('period', $period)->where('status', '!=', Import::CONFIRMED)->get()->each->delete();

            $import = Import::create([
                'distributor_id' => $distributor->id,
                'period' => $period,
                'status' => collect($checks)->every('ok') ? Import::NEEDS_REVIEW : Import::BLOCKED,
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
                'product_name' => $line['product'],
                'product_id' => $aliases[$line['product']] ?? null,
                'units' => $line['units'],
                'bonus' => $line['bonus'],
                'trade_price' => $line['trade_price'],
                'value' => $line['value'],
            ], $parsed->lines));

            return $import;
        });
    }

    /** @return list<array{key: string, ok: bool, message: string}> */
    private function checks(ParsedFile $parsed, Distributor $distributor, CarbonImmutable $period): array
    {
        $sameMonth = $parsed->periodFrom !== null && str_starts_with($parsed->periodFrom, $period->format('Y-m'));
        $firstWord = Str::of($distributor->name)->before(' ')->upper()->toString();
        $sameName = str_contains(mb_strtoupper($parsed->distributorInFile), $firstWord);

        return [
            ['key' => 'totals', 'ok' => $parsed->totalsMatch(), 'message' => $parsed->totalsMatch()
                ? 'Units and value match the grand total printed in the file.'
                : "Parsed {$parsed->parsedUnits()} units but the file's grand total says {$parsed->fileUnits}."],
            ['key' => 'period', 'ok' => $sameMonth, 'message' => $sameMonth
                ? 'The file covers '.$period->format('F Y').'.'
                : 'The file covers '.($parsed->periodFrom ?? 'an unknown period').', not '.$period->format('F Y').'.'],
            ['key' => 'distributor', 'ok' => $sameName, 'message' => $sameName
                ? 'The distributor name matches the file header.'
                : "The file header reads “{$parsed->distributorInFile}”, not {$distributor->name}."],
        ];
    }
}
