<?php

namespace App\Providers;

use App\Modules\Access\Enums\Role;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Support\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch N+1 queries and silent attribute typos while developing.
        Model::shouldBeStrict(! $this->app->isProduction());

        View::composer('components.layouts.app', function ($view) {
            $request = request();
            $period = Period::fromRequest($request);
            $query = ['period' => $period->format('Y-m')];
            $role = $request->user()?->role;

            $confirmed = Import::whereDate('period', $period)->where('status', Import::CONFIRMED)->distinct()->count('distributor_id');
            $pending = max(0, Distributor::count() - $confirmed);

            $items = [
                ['dashboard', 'Dashboard', 'dashboard', null],
                ['imports.index', 'Imports', 'imports.*', $pending ?: null],
                ['customers.index', 'Customers', 'customers.*', null],
                ['inventory.index', 'Inventory', 'inventory.*', null],
                ['reports.show', 'Reports', 'reports.*', null],
                ['products.index', 'Products & taxes', 'products.*', null],
            ];

            if ($role === Role::Owner) {
                $items[] = ['users.index', 'Users & roles', 'users.*', null];
            }

            $view->with('period', $period)->with('navItems', array_map(fn ($i) => [
                'url' => route($i[0], $query),
                'label' => $i[1],
                'active' => $request->routeIs($i[2]),
                'badge' => $i[3],
            ], $items));
        });
    }
}
