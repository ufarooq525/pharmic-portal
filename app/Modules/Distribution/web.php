<?php

use App\Modules\Distribution\Http\Controllers\Web\CustomerPageController;
use App\Modules\Distribution\Http\Controllers\Web\DistributorPageController;
use App\Modules\Distribution\Http\Controllers\Web\MasterDataController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerPageController::class, 'index'])->middleware('role:accounts,data_entry,viewer')->name('index');
    Route::put('{customer}/reps', [CustomerPageController::class, 'assignReps'])->middleware('role:accounts,data_entry')->name('reps');
});

Route::middleware('auth')->prefix('distributors')->name('distributors.')->group(function () {
    Route::get('/', [DistributorPageController::class, 'index'])->middleware('role:accounts,data_entry,viewer')->name('index');
    Route::put('{distributor}', [DistributorPageController::class, 'update'])->middleware('role:owner')->name('update');
    Route::delete('{distributor}/aliases/{alias}', [DistributorPageController::class, 'unlink'])->middleware('role:owner')->name('unlink');
});

Route::middleware('auth')->controller(MasterDataController::class)->group(function () {
    Route::get('master-data/reps', 'reps')->middleware('role:accounts,data_entry,viewer')->name('reps.index');
    Route::post('master-data/reps', 'storeRep')->middleware('role:accounts')->name('reps.store');
    Route::put('master-data/reps/{rep}', 'updateRep')->middleware('role:accounts')->name('reps.update');
    Route::get('master-data/name-matching', 'aliases')->middleware('role:accounts,data_entry,viewer')->name('aliases.index');
});
