<?php

namespace App\Modules\Access\Support;

use App\Models\User;
use App\Modules\Access\Enums\Permission as P;

/**
 * The sidebar, filtered by what the person may open. Also decides a person's home page: the
 * dashboard if they may see it, otherwise the first page they can open.
 */
class Navigation
{
    /**
     * Same groups and order as the approved Skyline board.
     * Each item: [route, label, active route patterns, permission, badge key].
     */
    private const GROUPS = [
        'Month end' => [
            ['dashboard', 'Dashboard', ['dashboard'], P::DashboardView, null],
            ['imports.index', 'Imports', ['imports.index'], P::ImportsView, 'pending'],
            ['imports.review', 'Import review', ['imports.review', 'imports.show'], P::ImportsView, 'toReview'],
            ['customers.index', 'Customers', ['customers.*'], P::CustomersView, null],
        ],
        'Stock & money' => [
            ['inventory.index', 'Inventory', ['inventory.*'], P::StockView, null],
            ['reports.show', 'Reports', ['reports.*'], P::DashboardView, null],
            ['invoices.index', 'Invoices', ['invoices.*'], P::InvoicesView, null],
            ['ledger.index', 'Distributor ledger', ['ledger.*'], P::LedgerView, null],
            ['payroll.index', 'MR payroll', ['payroll.*'], P::PayrollView, null],
        ],
        'Setup' => [
            ['master-data', 'Master data', ['products.*', 'distributors.*', 'reps.*', 'aliases.*'], null, null],
            ['users.index', 'Users & roles', ['users.*'], P::UsersManage, null],
            ['activity.index', 'Activity log', ['activity.*'], P::ActivityView, null],
        ],
    ];

    /** Master data tabs, in order: [key, route, label, permission]. */
    public const MASTER_TABS = [
        ['products', 'products.index', 'Products & taxes', P::ProductsView],
        ['distributors', 'distributors.index', 'Distributors & formats', P::DistributorsView],
        ['reps', 'reps.index', 'Medical reps', P::RepsView],
        ['aliases', 'aliases.index', 'Name matching', P::DistributorsView],
    ];

    /** @return array<string|int, list<array{0:string,1:string,2:list<string>,3:?string}>> group => [route, label, patterns, badge key] */
    public static function for(User $user): array
    {
        if ($user->isMedicalRep()) {
            return ['' => [['my.index', 'My territory', ['my.*'], null]]];
        }

        $groups = [];
        foreach (self::GROUPS as $group => $items) {
            foreach ($items as [$route, $label, $patterns, $permission, $badge]) {
                if ($route === 'master-data') {
                    $route = self::masterTabs($user)[0][1] ?? null;
                } elseif (! $user->hasPermission($permission)) {
                    $route = null;
                }
                if ($route) {
                    $groups[$group][] = [$route, $label, $patterns, $badge];
                }
            }
        }

        return $groups;
    }

    /** The master data tabs this person can open. */
    public static function masterTabs(User $user): array
    {
        return array_values(array_filter(self::MASTER_TABS, fn ($t) => $user->hasPermission($t[3])));
    }

    /** Route name of the page to land on after signing in, or null if they can open nothing. */
    public static function home(User $user): ?string
    {
        foreach (self::for($user) as $items) {
            return $items[0][0] ?? null;
        }

        return null;
    }
}
