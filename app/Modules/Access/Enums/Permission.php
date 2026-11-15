<?php

namespace App\Modules\Access\Enums;

use App\Support\Enums\HasOptions;

/**
 * Everything a person can be allowed to do. Roles get these by ticking them; a person can be
 * given extras on top of their role. The list is fixed in code because each one guards code.
 * Check with $user->can('imports.confirm'), @can in Blade, or the "permission:" route middleware.
 */
enum Permission: string
{
    use HasOptions;

    case DashboardView = 'dashboard.view';

    case ImportsView = 'imports.view';
    case ImportsUpload = 'imports.upload';
    case ImportsConfirm = 'imports.confirm';
    case ImportsRevert = 'imports.revert';

    case CustomersView = 'customers.view';
    case CustomersAssign = 'customers.assign';

    case DistributorsView = 'distributors.view';
    case DistributorsEdit = 'distributors.edit';
    case DistributorsUnlink = 'distributors.unlink';

    case RepsView = 'reps.view';
    case RepsEdit = 'reps.edit';

    case ProductsView = 'products.view';
    case ProductsRates = 'products.rates';

    case StockView = 'stock.view';
    case StockReceive = 'stock.receive';
    case StockRevert = 'stock.revert';

    case LedgerView = 'ledger.view';
    case LedgerRecord = 'ledger.record';
    case LedgerRemove = 'ledger.remove';

    case InvoicesView = 'invoices.view';
    case InvoicesIssue = 'invoices.issue';
    case InvoicesCancel = 'invoices.cancel';

    case PayrollView = 'payroll.view';
    case PayrollAdjust = 'payroll.adjust';

    case ActivityView = 'activity.view';
    case UsersManage = 'users.manage';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'View dashboard and reports',
            self::ImportsView => 'View imports',
            self::ImportsUpload => 'Upload, check and discard files',
            self::ImportsConfirm => 'Confirm an import',
            self::ImportsRevert => 'Revert an import',
            self::CustomersView => 'View customers',
            self::CustomersAssign => 'Assign reps to customers',
            self::DistributorsView => 'View distributors and name matching',
            self::DistributorsEdit => 'Edit distributor profile',
            self::DistributorsUnlink => 'Unlink product names',
            self::RepsView => 'View medical reps',
            self::RepsEdit => 'Add and edit medical reps',
            self::ProductsView => 'View products',
            self::ProductsRates => 'Change product rates',
            self::StockView => 'View inventory',
            self::StockReceive => 'Post a stock receipt',
            self::StockRevert => 'Revert a stock receipt',
            self::LedgerView => 'View and export ledger',
            self::LedgerRecord => 'Record a payment',
            self::LedgerRemove => 'Remove a payment',
            self::InvoicesView => 'View and print invoices',
            self::InvoicesIssue => 'Issue an invoice',
            self::InvoicesCancel => 'Cancel an invoice',
            self::PayrollView => 'View payroll',
            self::PayrollAdjust => 'Adjust payroll',
            self::ActivityView => 'View activity log',
            self::UsersManage => 'Manage users and roles',
        };
    }

    /** A short note under the label, where the label alone could mislead. */
    public function hint(): ?string
    {
        return match ($this) {
            self::ImportsUpload => 'Includes linking product names while reviewing a file.',
            self::ImportsConfirm => 'Posts the file’s sales to stock and customers.',
            self::StockView => 'Includes downloading the stock-file template.',
            self::PayrollView => 'Also shows payroll totals on the dashboard.',
            self::UsersManage => 'Can add people and change roles. They can only give permissions they have themselves.',
            default => null,
        };
    }

    public function group(): string
    {
        return match (explode('.', $this->value)[0]) {
            'dashboard' => 'Dashboard & reports',
            'imports' => 'Imports',
            'customers' => 'Customers',
            'distributors' => 'Distributors',
            'reps' => 'Medical reps',
            'products' => 'Products',
            'stock' => 'Stock',
            'ledger' => 'Ledger',
            'invoices' => 'Invoices',
            'payroll' => 'Payroll',
            default => 'Admin',
        };
    }

    /** The "view" permission an action needs, or null if it is a view itself. */
    public function requires(): ?self
    {
        return match ($this) {
            self::ImportsUpload, self::ImportsConfirm, self::ImportsRevert => self::ImportsView,
            self::CustomersAssign => self::CustomersView,
            self::DistributorsEdit, self::DistributorsUnlink => self::DistributorsView,
            self::RepsEdit => self::RepsView,
            self::ProductsRates => self::ProductsView,
            self::StockReceive, self::StockRevert => self::StockView,
            self::LedgerRecord, self::LedgerRemove => self::LedgerView,
            self::InvoicesIssue, self::InvoicesCancel => self::InvoicesView,
            self::PayrollAdjust => self::PayrollView,
            default => null,
        };
    }

    /** All permissions grouped for the screens: group label => list of permissions. @return array<string, list<self>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $p) {
            $groups[$p->group()][] = $p;
        }

        return $groups;
    }

    /**
     * Cleans a list of permission values: drops unknown ones and adds the "view" each action needs.
     *
     * @param  iterable<string|self>  $values
     * @return list<string>
     */
    public static function normalise(iterable $values): array
    {
        $out = [];
        foreach ($values as $v) {
            $p = $v instanceof self ? $v : self::tryFrom((string) $v);
            if ($p) {
                $out[$p->value] = true;
                if ($needs = $p->requires()) {
                    $out[$needs->value] = true;
                }
            }
        }

        return array_values(array_filter(array_map(fn (self $c) => isset($out[$c->value]) ? $c->value : null, self::cases())));
    }
}
