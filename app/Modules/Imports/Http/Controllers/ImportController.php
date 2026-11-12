<?php

namespace App\Modules\Imports\Http\Controllers;

use App\Modules\Distribution\Http\Resources\DistributorResource;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Imports\Actions\ConfirmImport;
use App\Modules\Imports\Actions\LinkProductName;
use App\Modules\Imports\Actions\ReadImport;
use App\Modules\Imports\Http\Requests\LinkProductNameRequest;
use App\Modules\Imports\Http\Requests\StoreImportRequest;
use App\Modules\Imports\Http\Resources\ImportDetailResource;
use App\Modules\Imports\Http\Resources\ImportResource;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Repositories\ImportRepository;
use App\Support\Http\Requests\PeriodRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** JSON API for imports (under /api/v1), using the same actions as the web screens. */
class ImportController
{
    /** Each distributor with its file for the month, if any. */
    public function index(PeriodRequest $request, ImportRepository $imports, DistributorRepository $distributors): JsonResponse
    {
        $period = $request->period();
        $byDistributor = $imports->latestPerDistributor($period);

        return response()->json(['data' => $distributors->visible($request->user()->visibleDistributorIds())->map(fn ($d) => [
            'distributor' => new DistributorResource($d),
            'import' => ($i = $byDistributor->get($d->id)) ? new ImportResource($i) : null,
        ])]);
    }

    public function store(StoreImportRequest $request, ReadImport $read): ImportDetailResource|JsonResponse
    {
        try {
            return new ImportDetailResource($read->handle($request->distributor(), $request->period(), $request->file('file'), $request->user())->load('distributor'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, Import $import): ImportDetailResource
    {
        abort_unless($request->user()->canSeeDistributor($import->distributor_id), 403);

        return new ImportDetailResource($import->load('distributor'));
    }

    /** Link a product spelling to a product once; it is remembered for this distributor. */
    public function mapProduct(LinkProductNameRequest $request, Import $import, LinkProductName $link): ImportDetailResource
    {
        $link->handle($import->load('distributor'), $request->validated('product_name'), $request->product());

        return new ImportDetailResource($import);
    }

    public function confirm(Request $request, Import $import, ConfirmImport $confirm): ImportResource|JsonResponse
    {
        try {
            $confirm->handle($import, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ImportResource($import->refresh());
    }
}
