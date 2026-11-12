<?php

namespace App\Modules\Distribution\Actions;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\MedicalRep;
use App\Support\Actions\Action;

/** One rep gets 100%; two reps share 50/50 (e.g. a store covered from Mardan 1 and Mardan 2). */
class AssignCustomerReps extends Action
{
    /** @param list<int> $repIds */
    public function handle(Customer $customer, array $repIds): void
    {
        $repIds = array_values(array_unique(array_filter($repIds)));
        $share = count($repIds) === 2 ? 50 : 100;

        $customer->medicalReps()->sync(collect($repIds)->mapWithKeys(fn ($id) => [$id => ['share_percent' => $share]]));
        $this->log('Assigned rep', $customer->name.': '.(MedicalRep::whereIn('id', $repIds)->pluck('name')->join(' + ') ?: 'no rep').($share === 50 ? ' (50/50)' : ''));
    }
}
