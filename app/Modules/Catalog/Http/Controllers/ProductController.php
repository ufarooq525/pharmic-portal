<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\AddProductRate;
use App\Modules\Catalog\Http\Requests\StoreProductRateRequest;
use App\Modules\Catalog\Http\Resources\ProductRateResource;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController
{
    public function index(ProductRepository $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products->withCurrentRate());
    }

    public function updateRate(StoreProductRateRequest $request, Product $product, AddProductRate $add): ProductRateResource
    {
        return new ProductRateResource($add->handle($product, $request->validated()));
    }
}
