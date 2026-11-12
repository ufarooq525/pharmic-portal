<?php

namespace App\Modules\Imports\Actions;

use App\Modules\Imports\Models\Import;
use App\Support\Actions\Action;
use DomainException;
use Illuminate\Support\Facades\Storage;

/** Throws away a file that was never confirmed (or was just reverted): the PDF and its rows go. */
class DiscardImport extends Action
{
    public function handle(Import $import): void
    {
        if ($import->isConfirmed()) {
            throw new DomainException('A confirmed import has to be reverted by the owner first.');
        }

        Storage::delete($import->file_path);
        $import->loadMissing('distributor:id,name')->delete();
        $this->log('Discarded file', "{$import->distributor->name}, {$import->period->format('F Y')}: {$import->original_name}");
    }
}
