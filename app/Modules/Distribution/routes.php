<?php

use App\Modules\Distribution\Http\Controllers\CustomerController;
use App\Modules\Distribution\Http\Controllers\DistributorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('distributors', [DistributorController::class, 'index'])->middleware('permission:distributors.view');
    Route::get('medical-reps', [DistributorController::class, 'medicalReps'])->middleware('permission:reps.view');
    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::put('customers/{customer}/reps', [CustomerController::class, 'assignReps'])->middleware('permission:customers.assign');
});
