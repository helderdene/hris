<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWithholdingTaxTableRequest;
use App\Http\Resources\WithholdingTaxTableResource;
use App\Models\WithholdingTaxTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WithholdingTaxController extends Controller
{
    /**
     * Display a listing of withholding tax tables.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $tables = WithholdingTaxTable::query()
            ->with(['brackets'])
            ->orderByDesc('effective_from')
            ->orderBy('pay_period')
            ->get();

        return WithholdingTaxTableResource::collection($tables);
    }

    /**
     * Store a newly created withholding tax table with brackets.
     */
    public function store(StoreWithholdingTaxTableRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $brackets = $validated['brackets'] ?? [];
        unset($validated['brackets']);

        $validated['created_by'] = auth()->id();

        $table = DB::transaction(function () use ($validated, $brackets) {
            $table = WithholdingTaxTable::create($validated);

            foreach ($brackets as $bracketData) {
                $table->brackets()->create($bracketData);
            }

            return $table;
        });

        $table->load('brackets');

        return (new WithholdingTaxTableResource($table))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified withholding tax table.
     */
    public function show(WithholdingTaxTable $withholdingTaxTable): WithholdingTaxTableResource
    {
        Gate::authorize('can-manage-organization');

        $withholdingTaxTable->load(['brackets']);

        return new WithholdingTaxTableResource($withholdingTaxTable);
    }

    /**
     * Update the specified withholding tax table with brackets.
     */
    public function update(StoreWithholdingTaxTableRequest $request, WithholdingTaxTable $withholdingTaxTable): WithholdingTaxTableResource
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $brackets = $validated['brackets'] ?? [];
        unset($validated['brackets']);

        DB::transaction(function () use ($withholdingTaxTable, $validated, $brackets) {
            $withholdingTaxTable->update($validated);

            // Replace all brackets with the new ones
            $withholdingTaxTable->brackets()->delete();

            foreach ($brackets as $bracketData) {
                $withholdingTaxTable->brackets()->create($bracketData);
            }
        });

        $withholdingTaxTable->load('brackets');

        return new WithholdingTaxTableResource($withholdingTaxTable);
    }

    /**
     * Remove the specified withholding tax table.
     */
    public function destroy(WithholdingTaxTable $withholdingTaxTable): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        DB::transaction(function () use ($withholdingTaxTable) {
            $withholdingTaxTable->brackets()->delete();
            $withholdingTaxTable->delete();
        });

        return response()->json([
            'message' => 'Withholding tax table deleted successfully.',
        ]);
    }
}
