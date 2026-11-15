<?php

use App\Modules\Imports\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:imports.view')->group(function () {
        Route::get('imports', [ImportController::class, 'index']);
        Route::get('imports/{import}', [ImportController::class, 'show']);
    });

    Route::middleware('permission:imports.upload')->group(function () {
        Route::post('imports', [ImportController::class, 'store']);
        Route::post('imports/{import}/map-product', [ImportController::class, 'mapProduct']);
    });

    Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->middleware('permission:imports.confirm');
});
