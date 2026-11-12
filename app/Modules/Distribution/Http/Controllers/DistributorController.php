<?php

namespace App\Modules\Distribution\Http\Controllers;

use App\Modules\Distribution\Http\Resources\DistributorResource;
use App\Modules\Distribution\Http\Resources\MedicalRepResource;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DistributorController
{
    public function index(Request $request, DistributorRepository $distributors): AnonymousResourceCollection
    {
        return DistributorResource::collection($distributors->visible($request->user()->visibleDistributorIds()));
    }

    public function medicalReps(MedicalRepRepository $reps): AnonymousResourceCollection
    {
        return MedicalRepResource::collection($reps->withTerritory());
    }
}
