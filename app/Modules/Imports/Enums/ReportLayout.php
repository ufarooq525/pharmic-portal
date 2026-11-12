<?php

namespace App\Modules\Imports\Enums;

use App\Support\Enums\HasOptions;

/** The three report formats distributor software prints. */
enum ReportLayout: string
{
    use HasOptions;

    case Matrix = 'matrix';
    case LineItem = 'lineitem';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Matrix => 'Product grid (SoftTouch "Customer wise sales report")',
            self::LineItem => 'Item lines with bonus (SoftTouch "Sale Report")',
            self::Monthly => 'Jan–Dec columns (customer annual sales)',
        };
    }

    /** Short name for chips. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Matrix => 'Grid',
            self::LineItem => 'Item lines',
            self::Monthly => 'Jan–Dec',
        };
    }
}
