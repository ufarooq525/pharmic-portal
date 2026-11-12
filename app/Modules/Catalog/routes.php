<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:accounts,data_entry,viewer'])->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products/{product}/rates', [ProductController::class, 'updateRate'])->middleware('role:owner');
});
