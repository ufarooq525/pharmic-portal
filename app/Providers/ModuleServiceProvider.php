<?php

namespace App\Providers;

use App\Modules\Access\Http\Middleware\EnsureRole;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Each folder in app/Modules is a self-contained module. A module may ship:
 *   web.php               -> Blade pages, loaded with the "web" middleware group
 *   routes.php            -> JSON API, loaded under /api/v1 with the "api" middleware group
 *   Resources/views       -> Blade views, namespaced by the module name: view('imports::review')
 *   Database/Migrations   -> picked up by "php artisan migrate"
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('role', EnsureRole::class);

        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $module) {
            $name = Str::kebab(basename($module));

            if (is_dir($migrations = $module.'/Database/Migrations')) {
                $this->loadMigrationsFrom($migrations);
            }

            if (is_dir($views = $module.'/Resources/views')) {
                $this->loadViewsFrom($views, $name);
            }

            if ($this->app->runningInConsole()) {
                foreach (glob($module.'/Console/*Command.php') as $command) {
                    $this->commands('App\\Modules\\'.basename($module).'\\Console\\'.basename($command, '.php'));
                }
            }

            if ($this->app->routesAreCached()) {
                continue;
            }

            if (is_file($web = $module.'/web.php')) {
                Route::middleware('web')->group($web);
            }

            if (is_file($api = $module.'/routes.php')) {
                Route::middleware('api')->prefix('api/v1')->group($api);
            }
        }
    }
}
