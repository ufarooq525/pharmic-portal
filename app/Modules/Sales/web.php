<?php

use App\Modules\Sales\Http\Controllers\Web\DashboardPageController;
use App\Modules\Sales\Http\Controllers\Web\InventoryController;
use App\Modules\Sales\Enums\ReportType;
use App\Modules\Sales\Http\Controllers\Web\ReportController;
use App\Modules\Sales\Http\Controllers\Web\StockReceiptController;
use Illuminate\Support\Facades\Route;

// Every signed-in role lands here; a medical rep is sent on to "My territory".
Route::get('dashboard', DashboardPageController::class)->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:accounts,data_entry,viewer'])->group(function () {
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('reports/{type?}', [ReportController::class, 'show'])->whereIn('type', ReportType::values())->name('reports.show');
    Route::get('reports/{type}/export', [ReportController::class, 'export'])->whereIn('type', ReportType::values())->name('reports.export');
});

Route::middleware('auth')->prefix('inventory/receipts')->name('inventory.receipts.')->controller(StockReceiptController::class)->group(function () {
    Route::get('template', 'template')->middleware('role:accounts,data_entry,viewer')->name('template');
    Route::post('/', 'store')->middleware('role:accounts')->name('store');
    Route::post('{receipt}/revert', 'revert')->middleware('role:accounts')->name('revert');
});
