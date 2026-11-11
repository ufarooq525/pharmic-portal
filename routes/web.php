<?php

use Illuminate\Support\Facades\Route;

/*
 * Pages live in their modules (app/Modules/<Module>/web.php) and are loaded by ModuleServiceProvider.
 */
// A named route, so the redirect also works when the app runs in a subfolder (localhost/pharmic-portal/public).
Route::get('/', fn () => redirect()->route('dashboard'));
