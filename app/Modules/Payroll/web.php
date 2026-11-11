<?php

use App\Modules\Payroll\Http\Controllers\Web\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
    Route::get('/', 'index')->middleware('role:accounts')->name('index');
    Route::put('{rep}', 'adjust')->middleware('role:accounts')->name('adjust');
    Route::get('{rep}/payslip', 'payslip')->name('payslip'); // checked in the controller: accounts or the rep themself
});

Route::get('my-territory', \App\Modules\Payroll\Http\Controllers\Web\MyTerritoryController::class)->middleware('auth')->name('my.index');
