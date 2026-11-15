<?php

use App\Modules\Sales\Enums\ReportType;
use App\Modules\Sales\Http\Controllers\Web\DashboardPageController;
use App\Modules\Sales\Http\Controllers\Web\InventoryController;
use App\Modules\Sales\Http\Controllers\Web\ReportController;
use App\Modules\Sales\Http\Controllers\Web\StockReceiptController;
use Illuminate\Support\Facades\Route;

// Everyone lands here after signing in; medical reps and people without the dashboard are sent on to their own home page.
Route::get('dashboard', DashboardPageController::class)->middleware(['auth', 'home'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('inventory', [InventoryController::class, 'index'])->middleware('permission:stock.view')->name('inventory.index');
    Route::get('inventory/{product}/card', [InventoryController::class, 'card'])->middleware('permission:stock.view')->name('inventory.card');
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('reports/{type?}', [ReportController::class, 'show'])->whereIn('type', ReportType::values())->name('reports.show');
        Route::get('reports/{type}/export', [ReportController::class, 'export'])->whereIn('type', ReportType::values())->name('reports.export');
    });
});

Route::middleware('auth')->prefix('inventory/receipts')->name('inventory.receipts.')->controller(StockReceiptController::class)->group(function () {
    Route::get('template', 'template')->middleware('permission:stock.view')->name('template');
    Route::post('/', 'store')->middleware('permission:stock.receive')->name('store');
    Route::post('{receipt}/revert', 'revert')->middleware('permission:stock.revert')->name('revert');
});
