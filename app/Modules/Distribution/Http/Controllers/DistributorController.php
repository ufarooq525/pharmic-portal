<?php

namespace App\Modules\Distribution\Http\Controllers;

use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use Illuminate\Http\JsonResponse;

class DistributorController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Distributor::orderBy('name')->get()]);
    }

    public function medicalReps(): JsonResponse
    {
        return response()->json(['data' => MedicalRep::with('territory:id,name')->orderBy('name')->get()]);
    }
}
