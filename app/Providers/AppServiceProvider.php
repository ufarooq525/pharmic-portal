<?php

namespace App\Providers;

use App\Modules\Imports\Enums\ImportStatus;
use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Support\Navigation;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Support\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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

        // In production a missed eager load must not break the page, but it must not go unnoticed either:
        // the query still runs and a warning names the model and relation, so it can be fixed.
        if ($this->app->isProduction()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
                logger()->warning('Lazy loading (add it to with()): '.$model::class.'::'.$relation, ['url' => request()->fullUrl()]);
            });
        }

        // Every Permission is a gate ability: $user->can('imports.confirm'), @can('ledger.record') in Blade.
        Gate::before(fn (User $user, string $ability) => Permission::tryFrom($ability) ? $user->hasPermission($ability) : null);

        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        View::composer('components.layouts.app', function ($view) {
            $request = request();
            $period = Period::fromRequest($request);
            $query = ['period' => $period->format('Y-m')];
            $user = $request->user();

            $confirmed = Import::whereDate('period', $period)->where('status', ImportStatus::Confirmed)->distinct()->count('distributor_id');
            $badges = [
                'pending' => max(0, Distributor::count() - $confirmed) ?: null,
                'toReview' => Import::whereDate('period', $period)->where('status', '!=', ImportStatus::Confirmed)->distinct()->count('distributor_id') ?: null,
            ];

            // Only the pages this person may open: [route, label, active patterns, badge].
            $groups = array_map(
                fn ($items) => array_map(fn ($i) => [$i[0], $i[1], $i[2], $i[3] ? $badges[$i[3]] : null], $items),
                $user ? Navigation::for($user) : [],
            );

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
