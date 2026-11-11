<?php

use App\Modules\Distribution\Http\Controllers\Web\CustomerPageController;
use App\Modules\Distribution\Http\Controllers\Web\DistributorPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerPageController::class, 'index'])->middleware('role:accounts,data_entry,viewer')->name('index');
    Route::put('{customer}/reps', [CustomerPageController::class, 'assignReps'])->middleware('role:accounts')->name('reps');
});

Route::middleware('auth')->prefix('distributors')->name('distributors.')->group(function () {
    Route::get('/', [DistributorPageController::class, 'index'])->middleware('role:accounts,data_entry,viewer')->name('index');
    Route::put('{distributor}', [DistributorPageController::class, 'update'])->middleware('role:owner')->name('update');
    Route::delete('{distributor}/aliases/{alias}', [DistributorPageController::class, 'unlink'])->middleware('role:owner')->name('unlink');
});
