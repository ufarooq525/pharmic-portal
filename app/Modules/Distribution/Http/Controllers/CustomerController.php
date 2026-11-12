<?php

namespace App\Modules\Distribution\Http\Controllers;

use App\Modules\Distribution\Http\Requests\AssignRepsApiRequest;
use App\Modules\Distribution\Http\Resources\CustomerResource;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Repositories\CustomerRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController
{
    public function index(Request $request, CustomerRepository $customers): AnonymousResourceCollection
    {
        $list = $customers->base($request->user()->visibleDistributorIds(), $request->integer('distributor_id') ?: null, $request->string('search')->trim()->toString())
            ->with(['distributor', 'medicalReps:id,name'])
            ->when($request->boolean('without_rep'), fn ($q) => $q->doesntHave('medicalReps'))
            ->orderBy('name')->paginate(50)->withQueryString();

        return CustomerResource::collection($list);
    }

    /** Replace the customer's reps; shares must add up to 100. */
    public function assignReps(AssignRepsApiRequest $request, Customer $customer): CustomerResource
    {
        $customer->medicalReps()->sync(collect($request->validated('reps'))->mapWithKeys(fn ($r) => [$r['medical_rep_id'] => ['share_percent' => $r['share_percent']]]));

        return new CustomerResource($customer->load('medicalReps:id,name'));
    }
}
