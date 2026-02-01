<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKpiTemplateRequest;
use App\Http\Requests\UpdateKpiTemplateRequest;
use App\Http\Resources\KpiTemplateResource;
use App\Models\KpiTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class KpiTemplateController extends Controller
{
    /**
     * Display a listing of KPI templates.
     *
     * Supports filtering by is_active and category.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = KpiTemplate::query()
            ->withCount('kpiAssignments')
            ->orderBy('category')
            ->orderBy('name');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Search by name or code
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return KpiTemplateResource::collection($query->get());
    }

    /**
     * Store a newly created KPI template.
     */
    public function store(StoreKpiTemplateRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $template = KpiTemplate::create($data);

        return (new KpiTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified KPI template.
     */
    public function show(string $tenant, KpiTemplate $kpiTemplate): KpiTemplateResource
    {
        Gate::authorize('can-manage-organization');

        $kpiTemplate->loadCount('kpiAssignments');

        return new KpiTemplateResource($kpiTemplate);
    }

    /**
     * Update the specified KPI template.
     */
    public function update(
        UpdateKpiTemplateRequest $request,
        string $tenant,
        KpiTemplate $kpiTemplate
    ): KpiTemplateResource {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $kpiTemplate->update($data);

        $kpiTemplate->loadCount('kpiAssignments');

        return new KpiTemplateResource($kpiTemplate);
    }

    /**
     * Remove the specified KPI template.
     *
     * Cannot delete if there are active assignments using this template.
     */
    public function destroy(string $tenant, KpiTemplate $kpiTemplate): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check if there are active assignments
        $activeAssignments = $kpiTemplate->kpiAssignments()
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        if ($activeAssignments > 0) {
            return response()->json([
                'message' => 'Cannot delete this KPI template because it has active assignments.',
            ], 422);
        }

        // Soft delete the template
        $kpiTemplate->delete();

        return response()->json([
            'message' => 'KPI template deleted successfully.',
        ]);
    }
}
