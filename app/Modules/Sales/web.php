<?php

use App\Modules\Sales\Http\Controllers\Web\DashboardPageController;
use App\Modules\Sales\Http\Controllers\Web\InventoryController;
use App\Modules\Sales\Http\Controllers\Web\ReportController;
use App\Modules\Sales\Http\Controllers\Web\StockReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:accounts,data_entry,viewer'])->group(function () {
    Route::get('dashboard', DashboardPageController::class)->name('dashboard');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('reports/{type?}', [ReportController::class, 'show'])->whereIn('type', ReportController::TYPES)->name('reports.show');
    Route::get('reports/{type}/export', [ReportController::class, 'export'])->whereIn('type', ReportController::TYPES)->name('reports.export');
});

Route::middleware('auth')->prefix('inventory/receipts')->name('inventory.receipts.')->controller(StockReceiptController::class)->group(function () {
    Route::get('template', 'template')->middleware('role:accounts,data_entry,viewer')->name('template');
    Route::post('/', 'store')->middleware('role:accounts')->name('store');
    Route::post('{receipt}/revert', 'revert')->middleware('role:accounts')->name('revert');
});
