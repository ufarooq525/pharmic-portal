<?php

use App\Modules\Sales\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class)->middleware('auth:sanctum');
