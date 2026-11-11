<?php

namespace App\Modules\Imports\Http\Controllers;

use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\ProductAlias;
use App\Modules\Imports\Http\Requests\StoreImportRequest;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Services\ConfirmImport;
use App\Modules\Imports\Services\ReadImport;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ImportController
{
    public function index(Request $request): JsonResponse
    {
        $period = CarbonImmutable::createFromFormat('!Y-m', $request->validate(['period' => ['required', 'date_format:Y-m']])['period']);
        $imports = Import::whereDate('period', $period)->latest('id')->get()->unique('distributor_id')->keyBy('distributor_id');

        return response()->json(['data' => Distributor::orderBy('name')->get()->map(fn (Distributor $d) => [
            'distributor' => $d->only('id', 'name', 'city', 'layout'),
            'import' => ($i = $imports->get($d->id)) ? $this->summary($i) : null,
        ])]);
    }

    public function store(StoreImportRequest $request, ReadImport $read): JsonResponse
    {
        try {
            $import = $read->handle(
                Distributor::findOrFail($request->integer('distributor_id')),
                CarbonImmutable::createFromFormat('!Y-m', $request->string('period')),
                $request->file('file'),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->detail($import)], 201);
    }

    public function show(Import $import): JsonResponse
    {
        return response()->json(['data' => $this->detail($import)]);
    }

    /** Link a product spelling to a product once; it is remembered for this distributor. */
    public function mapProduct(Request $request, Import $import): JsonResponse
    {
        $data = $request->validate(['product_name' => ['required', 'string'], 'product_id' => ['required', 'exists:products,id']]);

        ProductAlias::updateOrCreate(
            ['distributor_id' => $import->distributor_id, 'name_in_file' => $data['product_name']],
            ['product_id' => $data['product_id']],
        );
        $import->rows()->where('product_name', $data['product_name'])->update(['product_id' => $data['product_id']]);

        return response()->json(['data' => $this->detail($import)]);
    }

    public function confirm(Request $request, Import $import, ConfirmImport $confirm): JsonResponse
    {
        try {
            $confirm->handle($import, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->summary($import->refresh())]);
    }

    private function summary(Import $import): array
    {
        return $import->only('id', 'status', 'layout', 'original_name', 'distributor_in_file', 'file_units', 'file_value', 'parsed_units', 'parsed_value', 'checks', 'confirmed_at')
            + ['period' => $import->period->format('Y-m'), 'unmapped' => $import->rows()->whereNull('product_id')->distinct()->pluck('product_name')];
    }

    /** Rows grouped per customer, the way the review screen shows them. */
    private function detail(Import $import): array
    {
        $rows = $import->rows()->with('product:id,name')->get()->groupBy(fn ($r) => ($r->customer_code ?: $r->customer_name).'|'.$r->area);

        $customers = collect($import->customers)->map(function (array $c) use ($rows) {
            $lines = $rows->get((($c['code'] ?? null) ?: $c['name']).'|'.($c['area'] ?? ''), collect());

            return $c + [
                'units' => $lines->sum('units'),
                'value' => $lines->sum('value'),
                'lines' => $lines->map(fn ($r) => [
                    'product_name' => $r->product_name, 'product' => $r->product?->name, 'units' => $r->units,
                    'bonus' => $r->bonus, 'trade_price' => $r->trade_price, 'value' => $r->value,
                ])->values(),
            ];
        });

        return $this->summary($import) + ['distributor' => $import->distributor->only('id', 'name', 'city'), 'customers' => $customers];
    }
}
