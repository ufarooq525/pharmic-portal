<?php

use App\Modules\Payroll\Http\Controllers\Web\MyTerritoryController;
use App\Modules\Payroll\Http\Controllers\Web\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
    Route::get('/', 'index')->middleware('permission:payroll.view')->name('index');
    Route::put('{rep}', 'adjust')->middleware('permission:payroll.adjust')->name('adjust');
    Route::get('{rep}/payslip', 'payslip')->name('payslip'); // checked in the controller: payroll.view, or the rep themself
});

Route::get('my-territory', MyTerritoryController::class)->middleware('auth')->name('my.index');
