<?php

use App\Modules\Billing\Http\Controllers\Web\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
    Route::get('/', 'index')->middleware('permission:invoices.view')->name('index');
    Route::get('{invoice}/print', 'print')->middleware('permission:invoices.view')->name('print');
    Route::post('/', 'store')->middleware('permission:invoices.issue')->name('store');
    Route::post('{invoice}/cancel', 'cancel')->middleware('permission:invoices.cancel')->name('cancel');
});
