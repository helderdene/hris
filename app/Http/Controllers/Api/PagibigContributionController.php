<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePagibigContributionTableRequest;
use App\Http\Resources\PagibigContributionTableResource;
use App\Models\PagibigContributionTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PagibigContributionController extends Controller
{
    /**
     * Display a listing of Pag-IBIG contribution tables.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $tables = PagibigContributionTable::query()
            ->with(['tiers', 'creator'])
            ->orderByDesc('effective_from')
            ->get();

        return PagibigContributionTableResource::collection($tables);
    }

    /**
     * Store a newly created Pag-IBIG contribution table with tiers.
     */
    public function store(StorePagibigContributionTableRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $tiers = $validated['tiers'] ?? [];
        unset($validated['tiers']);

        $validated['created_by'] = auth()->id();

        $table = DB::transaction(function () use ($validated, $tiers) {
            $table = PagibigContributionTable::create($validated);

            foreach ($tiers as $tierData) {
                $table->tiers()->create($tierData);
            }

            return $table;
        });

        $table->load('tiers');

        return (new PagibigContributionTableResource($table))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified Pag-IBIG contribution table.
     */
    public function show(PagibigContributionTable $pagibigContributionTable): PagibigContributionTableResource
    {
        Gate::authorize('can-manage-organization');

        $pagibigContributionTable->load(['tiers', 'creator']);

        return new PagibigContributionTableResource($pagibigContributionTable);
    }

    /**
     * Update the specified Pag-IBIG contribution table with tiers.
     */
    public function update(StorePagibigContributionTableRequest $request, PagibigContributionTable $pagibigContributionTable): PagibigContributionTableResource
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $tiers = $validated['tiers'] ?? [];
        unset($validated['tiers']);

        DB::transaction(function () use ($pagibigContributionTable, $validated, $tiers) {
            $pagibigContributionTable->update($validated);

            // Replace all tiers with the new ones
            $pagibigContributionTable->tiers()->delete();

            foreach ($tiers as $tierData) {
                $pagibigContributionTable->tiers()->create($tierData);
            }
        });

        $pagibigContributionTable->load('tiers');

        return new PagibigContributionTableResource($pagibigContributionTable);
    }

    /**
     * Remove the specified Pag-IBIG contribution table.
     */
    public function destroy(PagibigContributionTable $pagibigContributionTable): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        DB::transaction(function () use ($pagibigContributionTable) {
            $pagibigContributionTable->tiers()->delete();
            $pagibigContributionTable->delete();
        });

        return response()->json([
            'message' => 'Pag-IBIG contribution table deleted successfully.',
        ]);
    }
}
