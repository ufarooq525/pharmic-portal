<?php

use App\Modules\Ledger\Http\Controllers\Web\LedgerPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('ledger')->name('ledger.')->controller(LedgerPageController::class)->group(function () {
    Route::get('/', 'index')->middleware('permission:ledger.view')->name('index');
    Route::get('{distributor}/export', 'export')->middleware('permission:ledger.view')->name('export');
    Route::post('{distributor}/payments', 'store')->middleware('permission:ledger.record')->name('payments.store');
    Route::delete('payments/{payment}', 'destroy')->middleware('permission:ledger.remove')->name('payments.destroy');
});
