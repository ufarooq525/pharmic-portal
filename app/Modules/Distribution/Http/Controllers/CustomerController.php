<?php

namespace App\Modules\Distribution\Http\Controllers;

use App\Modules\Distribution\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->with(['distributor:id,name', 'medicalReps:id,name'])
            ->when($request->integer('distributor_id'), fn ($q, $id) => $q->where('distributor_id', $id))
            ->when($request->boolean('without_rep'), fn ($q) => $q->doesntHave('medicalReps'))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(50);

        return response()->json($customers);
    }

    /** Replace the customer's reps. Shares must add up to 100. */
    public function assignReps(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'reps' => ['required', 'array', 'min:1', 'max:2'],
            'reps.*.medical_rep_id' => ['required', 'distinct', 'exists:medical_reps,id'],
            'reps.*.share_percent' => ['required', 'integer', 'between:1,100'],
        ]);

        abort_unless(array_sum(array_column($data['reps'], 'share_percent')) === 100, 422, 'Shares must add up to 100%.');

        $customer->medicalReps()->sync(collect($data['reps'])->mapWithKeys(fn ($r) => [$r['medical_rep_id'] => ['share_percent' => $r['share_percent']]]));

        return response()->json(['data' => $customer->load('medicalReps:id,name')]);
    }
}
