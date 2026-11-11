<?php

use App\Modules\Distribution\Http\Controllers\CustomerController;
use App\Modules\Distribution\Http\Controllers\DistributorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('distributors', [DistributorController::class, 'index']);
    Route::get('medical-reps', [DistributorController::class, 'medicalReps']);
    Route::get('customers', [CustomerController::class, 'index']);
    Route::put('customers/{customer}/reps', [CustomerController::class, 'assignReps'])->middleware('role:accounts');
});
