<?php

namespace App\Modules\Distribution\Actions;

use App\Modules\Distribution\Models\ProductAlias;
use App\Support\Actions\Action;

/** Forgets how a distributor spells a product; the next file using that spelling asks again. */
class UnlinkProductName extends Action
{
    public function handle(ProductAlias $alias): void
    {
        $alias->loadMissing('distributor:id,name')->delete();
        $this->log('Unlinked product name', "{$alias->distributor->name}: “{$alias->name_in_file}”");
    }
}
