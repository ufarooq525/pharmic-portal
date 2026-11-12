<?php

namespace App\Modules\Imports\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\ProductAlias;
use App\Modules\Imports\Models\Import;
use App\Support\Actions\Action;

/**
 * Links how this distributor spells a product to the product, once; later files use it
 * automatically. The distributor's item code is kept too when the file prints exactly one for
 * this name, so a later file that re-spells the product is still recognised.
 */
class LinkProductName extends Action
{
    public function handle(Import $import, string $nameInFile, Product $product): void
    {
        $this->transaction(function () use ($import, $nameInFile, $product) {
            $codes = $import->rows()->where('product_name', $nameInFile)->whereNotNull('product_code')->distinct()->pluck('product_code');

            ProductAlias::updateOrCreate(
                ['distributor_id' => $import->distributor_id, 'name_in_file' => $nameInFile],
                ['product_id' => $product->id, 'code_in_file' => $codes->count() === 1 ? $codes->first() : null],
            );
            $import->rows()->where('product_name', $nameInFile)->update(['product_id' => $product->id]);
        });

        $this->log('Linked product name', "{$import->distributor->name}: “{$nameInFile}” → {$product->name}");
    }
}
