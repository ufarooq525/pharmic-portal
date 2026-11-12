<?php

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Actions\AddProductRate;
use App\Modules\Catalog\Http\Requests\StoreProductRateRequest;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductPageController
{
    public function index(Request $request, ProductRepository $products): View
    {
        return view('catalog::products', [
            'period' => Period::fromRequest($request),
            'products' => $products->withRatesAndAliases(),
            'canEdit' => $request->user()->hasRole(),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function storeRate(StoreProductRateRequest $request, Product $product, AddProductRate $add): RedirectResponse
    {
        $rate = $add->handle($product, $request->validated());

        return redirect()->route('products.index', ['period' => $request->query('period')])
            ->with('status', "{$product->name}: new rates apply from ".$rate->effective_from->format('j M Y').'.');
    }
}
