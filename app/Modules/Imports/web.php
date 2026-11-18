<?php

use App\Modules\Imports\Http\Controllers\Web\ImportPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('imports')->name('imports.')->controller(ImportPageController::class)->group(function () {
    Route::middleware('permission:imports.view')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('review', 'review')->name('review');
        Route::get('{import}', 'show')->whereNumber('import')->name('show');
        Route::get('{import}/file', 'file')->name('file');
        Route::get('{import}/view', 'view')->name('view');
        Route::get('{import}/stock-report', 'stockReportFile')->name('stock-report.file');
        Route::get('{import}/customers/{index}', 'customer')->whereNumber('index')->name('customer');
    });

    // Uploading and fixing a file, confirming it (posts to stock) and undoing it are separate permissions.
    Route::middleware('permission:imports.upload')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('{import}/map', 'map')->name('map');
        Route::post('{import}/stock-report', 'stockReport')->name('stock-report');
        Route::delete('{import}', 'discard')->name('discard');
    });
    Route::post('{import}/confirm', 'confirm')->middleware('permission:imports.confirm')->name('confirm');
    Route::post('{import}/reverse', 'reverse')->middleware('permission:imports.revert')->name('reverse');
});
