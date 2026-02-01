<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSssContributionTableRequest;
use App\Http\Resources\SssContributionTableResource;
use App\Models\SssContributionTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SssContributionController extends Controller
{
    /**
     * Display a listing of SSS contribution tables.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $tables = SssContributionTable::query()
            ->with(['brackets', 'creator'])
            ->orderByDesc('effective_from')
            ->get();

        return SssContributionTableResource::collection($tables);
    }

    /**
     * Store a newly created SSS contribution table with brackets.
     */
    public function store(StoreSssContributionTableRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $brackets = $validated['brackets'] ?? [];
        unset($validated['brackets']);

        $validated['created_by'] = auth()->id();

        $table = DB::transaction(function () use ($validated, $brackets) {
            $table = SssContributionTable::create($validated);

            foreach ($brackets as $bracketData) {
                $table->brackets()->create($bracketData);
            }

            return $table;
        });

        $table->load('brackets');

        return (new SssContributionTableResource($table))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified SSS contribution table.
     */
    public function show(SssContributionTable $sssContributionTable): SssContributionTableResource
    {
        Gate::authorize('can-manage-organization');

        $sssContributionTable->load(['brackets', 'creator']);

        return new SssContributionTableResource($sssContributionTable);
    }

    /**
     * Update the specified SSS contribution table with brackets.
     */
    public function update(StoreSssContributionTableRequest $request, SssContributionTable $sssContributionTable): SssContributionTableResource
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $brackets = $validated['brackets'] ?? [];
        unset($validated['brackets']);

        DB::transaction(function () use ($sssContributionTable, $validated, $brackets) {
            $sssContributionTable->update($validated);

            // Replace all brackets with the new ones
            $sssContributionTable->brackets()->delete();

            foreach ($brackets as $bracketData) {
                $sssContributionTable->brackets()->create($bracketData);
            }
        });

        $sssContributionTable->load('brackets');

        return new SssContributionTableResource($sssContributionTable);
    }

    /**
     * Remove the specified SSS contribution table.
     */
    public function destroy(SssContributionTable $sssContributionTable): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        DB::transaction(function () use ($sssContributionTable) {
            $sssContributionTable->brackets()->delete();
            $sssContributionTable->delete();
        });

        return response()->json([
            'message' => 'SSS contribution table deleted successfully.',
        ]);
    }
}
