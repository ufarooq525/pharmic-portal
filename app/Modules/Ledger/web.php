<?php

use App\Modules\Ledger\Http\Controllers\Web\LedgerPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('ledger')->name('ledger.')->controller(LedgerPageController::class)->group(function () {
    Route::get('/', 'index')->middleware('role:accounts,viewer')->name('index');
    Route::get('{distributor}/export', 'export')->middleware('role:accounts,viewer')->name('export');
    Route::post('{distributor}/payments', 'store')->middleware('role:accounts')->name('payments.store');
    Route::delete('payments/{payment}', 'destroy')->middleware('role:owner')->name('payments.destroy');
});
