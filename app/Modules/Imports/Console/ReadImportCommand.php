<?php

namespace App\Modules\Imports\Console;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Parsers\PdfImportReader;
use App\Modules\Imports\Actions\ConfirmImport;
use App\Modules\Imports\Actions\ReadImport;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Import a distributor PDF from the command line, the same way the Imports page does.
 *   php artisan imports:read Hamdan "C:/.../CUSTOMER WISE (2).PDF" --confirm
 */
class ReadImportCommand extends Command
{
    protected $signature = 'imports:read
        {distributor : First word of the distributor name, e.g. Hamdan}
        {file : Path to the PDF}
        {--period= : Month as YYYY-MM (default: the month printed in the file)}
        {--confirm : Post it straight away if every check passes}
        {--user= : Email of the user recorded as uploader (default: the first owner)}';

    protected $description = 'Read a distributor customer-wise PDF into the review queue';

    public function handle(ReadImport $read, ConfirmImport $confirm, PdfImportReader $reader): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("No file at {$path}");

            return self::FAILURE;
        }

        $distributor = Distributor::where('name', 'like', $this->argument('distributor').'%')->first();
        $user = $this->option('user') ? User::where('email', $this->option('user'))->first() : User::where('role', Role::Owner->value)->first();

        if (! $distributor || ! $user) {
            $this->error(! $distributor ? 'No distributor starts with “'.$this->argument('distributor').'”.' : 'No user to record as the uploader. Run php artisan db:seed first.');

            return self::FAILURE;
        }

        try {
            $period = $this->option('period')
                ? CarbonImmutable::createFromFormat('!Y-m', $this->option('period'))
                : CarbonImmutable::parse($reader->read($path)->periodFrom ?? throw new RuntimeException('The file has no From Date; pass --period.'))->startOfMonth();

            $import = $read->handle($distributor, $period, new UploadedFile($path, basename($path), 'application/pdf', null, true), $user);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($import->checks as $check) {
            $this->line(($check['ok'] ? '<info>✓</info> ' : '<error>✕</error> ').$check['message']);
        }

        $unmapped = $import->rows()->whereNull('product_id')->distinct()->pluck('product_name');
        $this->line("{$distributor->name} · {$period->format('F Y')} · ".count($import->customers).' customers · '.$import->parsed_units.' units · status: '.$import->status);

        if ($unmapped->isNotEmpty()) {
            $this->warn('Link these product names on the review page: '.$unmapped->join(', '));
        }

        if ($this->option('confirm')) {
            try {
                $confirm->handle($import, $user);
                $this->info('Confirmed and posted.');
            } catch (DomainException $e) {
                $this->warn('Not confirmed: '.$e->getMessage());
            }
        }

        $this->line('Review: '.route('imports.show', $import));

        return self::SUCCESS;
    }
}
