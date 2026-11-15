<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::post('products/{product}/rates', [ProductController::class, 'updateRate'])->middleware('permission:products.rates');
});
