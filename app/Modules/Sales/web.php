<?php

use App\Modules\Sales\Http\Controllers\Web\DashboardPageController;
use App\Modules\Sales\Http\Controllers\Web\InventoryController;
use App\Modules\Sales\Http\Controllers\Web\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:accounts,data_entry,viewer'])->group(function () {
    Route::get('dashboard', DashboardPageController::class)->name('dashboard');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('reports/{type?}', [ReportController::class, 'show'])->whereIn('type', ReportController::TYPES)->name('reports.show');
    Route::get('reports/{type}/export', [ReportController::class, 'export'])->whereIn('type', ReportController::TYPES)->name('reports.export');
});
