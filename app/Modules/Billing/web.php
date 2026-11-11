<?php

use App\Modules\Billing\Http\Controllers\Web\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
    Route::get('/', 'index')->middleware('role:accounts')->name('index');
    Route::get('{invoice}/print', 'print')->middleware('role:accounts')->name('print');
    Route::post('/', 'store')->middleware('role:accounts')->name('store');
    Route::post('{invoice}/cancel', 'cancel')->middleware('role:owner')->name('cancel');
});
