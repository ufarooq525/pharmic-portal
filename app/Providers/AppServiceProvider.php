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

            // Same order as the approved Skyline board: month end, stock and money, setup.
            $items = [
                ['dashboard', 'Dashboard', ['dashboard'], null],
                ['imports.index', 'Imports', ['imports.*'], $pending ?: null],
                ['customers.index', 'Customers', ['customers.*'], null],
                ['inventory.index', 'Inventory', ['inventory.*'], null],
                ['reports.show', 'Reports', ['reports.*'], null],
                ...(in_array($role, [Role::Owner, Role::Accounts], true) ? [['invoices.index', 'Invoices', ['invoices.*'], null]] : []),
                ...(in_array($role, [Role::Owner, Role::Accounts, Role::Viewer], true) ? [['ledger.index', 'Distributor ledger', ['ledger.*'], null]] : []),
                ...(in_array($role, [Role::Owner, Role::Accounts], true) ? [['payroll.index', 'MR payroll', ['payroll.*'], null]] : []),
                ['products.index', 'Master data', ['products.*', 'distributors.*', 'reps.*', 'aliases.*'], null],
            ];

            if ($role === Role::MedicalRep) {
                $items = [['my.index', 'My territory', ['my.*'], null]];
            }

            if ($role === Role::Owner) {
                $items[] = ['users.index', 'Users & roles', ['users.*'], null];
            }
            if (in_array($role, [Role::Owner, Role::Accounts], true)) {
                $items[] = ['activity.index', 'Activity log', ['activity.*'], null];
            }

            $view->with('period', $period)->with('navItems', array_map(fn ($i) => [
                'route' => $i[0],
                'url' => route($i[0], $query),
                'label' => $i[1],
                'active' => $request->routeIs(...$i[2]),
                'badge' => $i[3],
            ], $items));
        });
    }
}
