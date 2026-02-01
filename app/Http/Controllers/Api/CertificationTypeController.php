<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCertificationTypeRequest;
use App\Http\Requests\UpdateCertificationTypeRequest;
use App\Http\Resources\CertificationTypeResource;
use App\Models\CertificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CertificationTypeController extends Controller
{
    /**
     * Display a listing of certification types.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CertificationType::query()
            ->orderBy('name');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->has('mandatory')) {
            $query->where('is_mandatory', $request->boolean('mandatory'));
        }

        return CertificationTypeResource::collection($query->get());
    }

    /**
     * Store a newly created certification type.
     */
    public function store(StoreCertificationTypeRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $certificationType = CertificationType::create($request->validated());

        return (new CertificationTypeResource($certificationType))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified certification type.
     */
    public function show(string $tenant, CertificationType $certificationType): CertificationTypeResource
    {
        return new CertificationTypeResource($certificationType);
    }

    /**
     * Update the specified certification type.
     */
    public function update(
        UpdateCertificationTypeRequest $request,
        string $tenant,
        CertificationType $certificationType
    ): CertificationTypeResource {
        Gate::authorize('can-manage-organization');

        $certificationType->update($request->validated());

        return new CertificationTypeResource($certificationType);
    }

    /**
     * Remove the specified certification type (soft delete).
     */
    public function destroy(string $tenant, CertificationType $certificationType): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check if there are any certifications using this type
        if ($certificationType->certifications()->exists()) {
            return response()->json([
                'message' => 'Cannot delete certification type that has certifications.',
            ], 422);
        }

        $certificationType->delete();

        return response()->json([
            'message' => 'Certification type deleted successfully.',
        ]);
    }
}
