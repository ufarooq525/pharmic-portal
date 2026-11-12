<?php

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The dashboard figures (same source as the web dashboard) as JSON. */
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $d = $this->resource;

        return [
            'period' => $d['period'],
            'totals' => $d['totals'],
            'deltas' => $d['deltas'],
            'trend' => $d['trend'],
            'by_distributor' => $d['byDistributor'],
            'by_product' => $d['byProduct'],
            'by_rep' => $d['byRep'],
            'top_customers' => $d['topCustomers'],
            'checklist' => $d['checklist'],
            'stock' => $d['stock'],
            'actions' => $d['actions'],
        ];
    }
}
