<?php

namespace App\Providers;

use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Access\Enums\Role;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Support\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
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
        // Strict mode outside production: lazy loading (N+1), silently discarded attributes and missing
        // attributes throw, so they are caught while developing.
        Model::shouldBeStrict(! $this->app->isProduction());
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        View::composer('components.layouts.app', function ($view) {
            $request = request();
            $period = Period::fromRequest($request);
            $query = ['period' => $period->format('Y-m')];
            $role = $request->user()?->role;

            $confirmed = Import::whereDate('period', $period)->where('status', ImportStatus::Confirmed)->distinct()->count('distributor_id');
            $pending = max(0, Distributor::count() - $confirmed);

            $toReview = Import::whereDate('period', $period)->where('status', '!=', ImportStatus::Confirmed)->distinct()->count('distributor_id');
            $can = fn (Role ...$roles) => in_array($role, $roles, true);

            // Same groups and order as the approved Skyline board: [route, label, active patterns, badge].
            $groups = $role === Role::MedicalRep
                ? [null => [['my.index', 'My territory', ['my.*'], null]]]
                : array_filter([
                    'Month end' => [
                        ['dashboard', 'Dashboard', ['dashboard'], null],
                        ['imports.index', 'Imports', ['imports.index'], $pending ?: null],
                        ['imports.review', 'Import review', ['imports.review', 'imports.show'], $toReview ?: null],
                        ['customers.index', 'Customers', ['customers.*'], null],
                    ],
                    'Stock & money' => array_values(array_filter([
                        ['inventory.index', 'Inventory', ['inventory.*'], null],
                        ['reports.show', 'Reports', ['reports.*'], null],
                        $can(Role::Owner, Role::Accounts) ? ['invoices.index', 'Invoices', ['invoices.*'], null] : null,
                        $can(Role::Owner, Role::Accounts, Role::Viewer) ? ['ledger.index', 'Distributor ledger', ['ledger.*'], null] : null,
                        $can(Role::Owner, Role::Accounts) ? ['payroll.index', 'MR payroll', ['payroll.*'], null] : null,
                    ])),
                    'Setup' => array_values(array_filter([
                        ['products.index', 'Master data', ['products.*', 'distributors.*', 'reps.*', 'aliases.*'], null],
                        $can(Role::Owner) ? ['users.index', 'Users & roles', ['users.*'], null] : null,
                        $can(Role::Owner, Role::Accounts) ? ['activity.index', 'Activity log', ['activity.*'], null] : null,
                    ])),
                ]);

            $view->with('period', $period)->with('navGroups', array_map(fn ($items) => array_map(fn ($i) => [
                'route' => $i[0],
                'url' => route($i[0], $query),
                'label' => $i[1],
                'active' => $request->routeIs(...$i[2]),
                'badge' => $i[3],
            ], $items), $groups));
        });
    }
}
