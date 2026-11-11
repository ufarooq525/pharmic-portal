<?php

use App\Modules\Imports\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('imports', [ImportController::class, 'index']);
    Route::get('imports/{import}', [ImportController::class, 'show']);

    Route::middleware('role:accounts,data_entry')->group(function () {
        Route::post('imports', [ImportController::class, 'store']);
        Route::post('imports/{import}/map-product', [ImportController::class, 'mapProduct']);
    });

    // Data entry can upload and fix; only Accounts and the Owner post to stock.
    Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->middleware('role:accounts');
});
