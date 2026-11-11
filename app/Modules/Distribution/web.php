<?php

use App\Modules\Distribution\Http\Controllers\Web\CustomerPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerPageController::class, 'index'])->middleware('role:accounts,data_entry,viewer')->name('index');
    Route::put('{customer}/reps', [CustomerPageController::class, 'assignReps'])->middleware('role:accounts')->name('reps');
});
