<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Product::with('currentRate')->orderBy('name')->get()]);
    }

    /** A rate change is stored as a new dated row; earlier months keep their own rates. */
    public function updateRate(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'exfp_price' => ['required', 'numeric', 'min:0'],
            'trade_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sales_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'withholding_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'incentive_rate' => ['required', 'numeric', 'between:0,100'],
            'bonus_buy' => ['required', 'integer', 'min:0'],
            'bonus_free' => ['required', 'integer', 'min:0'],
        ]);

        $rate = $product->rates()->updateOrCreate(['effective_from' => $data['effective_from']], $data);

        return response()->json(['data' => $rate], 201);
    }
}
