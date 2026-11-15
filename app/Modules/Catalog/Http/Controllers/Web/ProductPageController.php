<?php

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Support\Paginate;
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
            'products' => $list = $products->withRatesAndAliases(),
            'productsPage' => Paginate::collection($list, 25),
            'canEdit' => $request->user()->can('products.rates'),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function storeRate(StoreProductRateRequest $request, Product $product, AddProductRate $add): RedirectResponse
    {
        $rate = $add->handle($product, $request->validated());

        // Back to where the rate was changed (Products, or the Inventory drawer).
        return redirect()->back(302, [], route('products.index', ['period' => $request->query('period')]))
            ->with('status', "{$product->name}: new rates apply from ".$rate->effective_from->format('j M Y').'.');
    }
}
