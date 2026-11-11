<?php

use App\Modules\Imports\Http\Controllers\Web\ImportPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('imports')->name('imports.')->controller(ImportPageController::class)->group(function () {
    Route::middleware('role:accounts,data_entry,viewer')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{import}', 'show')->name('show');
        Route::get('{import}/file', 'file')->name('file');
    });

    // Data entry can upload and fix; only Accounts and the Owner post to stock; only the Owner undoes.
    Route::middleware('role:accounts,data_entry')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('{import}/map', 'map')->name('map');
    });
    Route::post('{import}/confirm', 'confirm')->middleware('role:accounts')->name('confirm');
    Route::post('{import}/reverse', 'reverse')->middleware('role:owner')->name('reverse');
});
