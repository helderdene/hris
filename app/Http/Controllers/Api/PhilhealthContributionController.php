<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePhilhealthContributionTableRequest;
use App\Http\Resources\PhilhealthContributionTableResource;
use App\Models\PhilhealthContributionTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PhilhealthContributionController extends Controller
{
    /**
     * Display a listing of PhilHealth contribution tables.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $tables = PhilhealthContributionTable::query()
            ->with('creator')
            ->orderByDesc('effective_from')
            ->get();

        return PhilhealthContributionTableResource::collection($tables);
    }

    /**
     * Store a newly created PhilHealth contribution table.
     */
    public function store(StorePhilhealthContributionTableRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $validated['created_by'] = auth()->id();

        $table = PhilhealthContributionTable::create($validated);

        return (new PhilhealthContributionTableResource($table))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified PhilHealth contribution table.
     */
    public function show(PhilhealthContributionTable $philhealthContributionTable): PhilhealthContributionTableResource
    {
        Gate::authorize('can-manage-organization');

        $philhealthContributionTable->load('creator');

        return new PhilhealthContributionTableResource($philhealthContributionTable);
    }

    /**
     * Update the specified PhilHealth contribution table.
     */
    public function update(StorePhilhealthContributionTableRequest $request, PhilhealthContributionTable $philhealthContributionTable): PhilhealthContributionTableResource
    {
        Gate::authorize('can-manage-organization');

        $philhealthContributionTable->update($request->validated());

        return new PhilhealthContributionTableResource($philhealthContributionTable);
    }

    /**
     * Remove the specified PhilHealth contribution table.
     */
    public function destroy(PhilhealthContributionTable $philhealthContributionTable): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $philhealthContributionTable->delete();

        return response()->json([
            'message' => 'PhilHealth contribution table deleted successfully.',
        ]);
    }
}
