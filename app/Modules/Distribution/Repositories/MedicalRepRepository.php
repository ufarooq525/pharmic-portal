<?php

namespace App\Modules\Distribution\Repositories;

use App\Modules\Distribution\Models\MedicalRep;
use App\Support\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class MedicalRepRepository extends Repository
{
    protected function model(): string
    {
        return MedicalRep::class;
    }

    public function withTerritory(bool $activeOnly = false): Collection
    {
        return $this->query()->with('territory:id,name')->when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name')->get();
    }

    /** rep id => number of customers linked, in one grouped query. */
    public function customerCounts(): BaseCollection
    {
        return DB::table('customer_medical_rep')->groupBy('medical_rep_id')->selectRaw('medical_rep_id, COUNT(*) AS n')->pluck('n', 'medical_rep_id');
    }
}
