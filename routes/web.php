<?php

use Illuminate\Support\Facades\Route;

/*
 * Pages live in their modules (app/Modules/<Module>/web.php) and are loaded by ModuleServiceProvider.
 */
Route::redirect('/', '/dashboard');
