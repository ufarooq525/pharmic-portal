<?php

namespace App\Modules\Sales\Enums;

use App\Support\Enums\HasOptions;

enum ReportType: string
{
    use HasOptions;

    case Sales = 'sales';
    case Customers = 'customers';
    case Bonus = 'bonus';
    case Incentive = 'incentive';
    case Taxes = 'taxes';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales by product',
            self::Customers => 'Customer-wise sales',
            self::Bonus => 'Bonus given',
            self::Incentive => 'Percentage incentive by medical rep',
            self::Taxes => 'Sales tax and withholding tax',
        };
    }
}
