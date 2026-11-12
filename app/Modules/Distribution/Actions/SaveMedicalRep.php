<?php

namespace App\Modules\Distribution\Actions;

use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Distribution\Models\Territory;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use App\Support\Actions\Action;

/** Adds a rep or updates one. A new territory name creates the territory. */
class SaveMedicalRep extends Action
{
    public function __construct(private readonly MedicalRepRepository $reps) {}

    /** @param array{name: string, territory: string, phone?: ?string, base_salary?: ?float, is_active?: bool} $data */
    public function handle(array $data, ?MedicalRep $rep = null): MedicalRep
    {
        $attributes = [
            'name' => $data['name'],
            'territory_id' => Territory::firstOrCreate(['name' => trim($data['territory'])])->id,
            'phone' => $data['phone'] ?? null,
            'base_salary' => $data['base_salary'] ?? 0,
        ];

        if ($rep) {
            $this->reps->update($rep, $attributes + ['is_active' => (bool) ($data['is_active'] ?? false)]);
            $this->log('Changed medical rep', "{$rep->name}, {$rep->territory->name}".($rep->is_active ? '' : ', inactive'));

            return $rep;
        }

        $rep = $this->reps->create($attributes);
        $this->log('Added medical rep', "{$rep->name}, {$rep->territory->name}");

        return $rep;
    }
}
