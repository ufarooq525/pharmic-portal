<?php

use App\Modules\Distribution\Http\Controllers\Web\CustomerPageController;
use App\Modules\Distribution\Http\Controllers\Web\DistributorPageController;
use App\Modules\Distribution\Http\Controllers\Web\MasterDataController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerPageController::class, 'index'])->middleware('permission:customers.view')->name('index');
    Route::put('{customer}/reps', [CustomerPageController::class, 'assignReps'])->middleware('permission:customers.assign')->name('reps');
});

Route::middleware('auth')->prefix('distributors')->name('distributors.')->group(function () {
    Route::get('/', [DistributorPageController::class, 'index'])->middleware('permission:distributors.view')->name('index');
    Route::put('{distributor}', [DistributorPageController::class, 'update'])->middleware('permission:distributors.edit')->name('update');
    Route::delete('{distributor}/aliases/{alias}', [DistributorPageController::class, 'unlink'])->middleware('permission:distributors.unlink')->name('unlink');
});

Route::middleware('auth')->controller(MasterDataController::class)->group(function () {
    Route::get('master-data/reps', 'reps')->middleware('permission:reps.view')->name('reps.index');
    Route::post('master-data/reps', 'storeRep')->middleware('permission:reps.edit')->name('reps.store');
    Route::put('master-data/reps/{rep}', 'updateRep')->middleware('permission:reps.edit')->name('reps.update');
    Route::get('master-data/name-matching', 'aliases')->middleware('permission:distributors.view')->name('aliases.index');
});
