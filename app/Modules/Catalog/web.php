<?php

use App\Modules\Catalog\Http\Controllers\Web\ProductPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductPageController::class, 'index'])->middleware('permission:products.view')->name('index');
    Route::post('{product}/rates', [ProductPageController::class, 'storeRate'])->middleware('permission:products.rates')->name('rates');
});
