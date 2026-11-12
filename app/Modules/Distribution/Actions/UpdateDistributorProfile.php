<?php

namespace App\Modules\Distribution\Actions;

use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Support\Actions\Action;

class UpdateDistributorProfile extends Action
{
    public function __construct(private readonly DistributorRepository $distributors) {}

    public function handle(Distributor $distributor, array $data): Distributor
    {
        $this->distributors->update($distributor, $data);
        $this->log('Changed distributor profile', "{$distributor->name}: format ".($distributor->layout?->label() ?? 'not set').', bonus: '.$distributor->bonus_source->label());

        return $distributor;
    }
}
