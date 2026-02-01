<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplianceModuleRequest;
use App\Http\Requests\UpdateComplianceModuleRequest;
use App\Http\Resources\ComplianceModuleResource;
use App\Models\ComplianceCourse;
use App\Models\ComplianceModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ComplianceModuleController extends Controller
{
    /**
     * Display a listing of modules for a compliance course.
     */
    public function index(
        Request $request,
        string $tenant,
        ComplianceCourse $complianceCourse
    ): AnonymousResourceCollection {
        Gate::authorize('can-view-training');

        $modules = $complianceCourse->modules()
            ->with(['assessments'])
            ->orderBy('sort_order')
            ->get();

        return ComplianceModuleResource::collection($modules);
    }

    /**
     * Store a newly created module.
     */
    public function store(
        StoreComplianceModuleRequest $request,
        string $tenant,
        ComplianceCourse $complianceCourse
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $validated['compliance_course_id'] = $complianceCourse->id;

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('compliance-modules', 'private');

            $validated['file_path'] = $path;
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['file_size'] = $file->getSize();
            $validated['mime_type'] = $file->getMimeType();
        }

        // Set sort order if not provided
        if (! isset($validated['sort_order'])) {
            $maxOrder = $complianceCourse->modules()->max('sort_order') ?? 0;
            $validated['sort_order'] = $maxOrder + 1;
        }

        $module = ComplianceModule::create($validated);
        $module->load('assessments');

        return (new ComplianceModuleResource($module))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified module.
     */
    public function show(
        string $tenant,
        ComplianceCourse $complianceCourse,
        ComplianceModule $complianceModule
    ): ComplianceModuleResource {
        Gate::authorize('can-view-training');

        // Ensure module belongs to the course
        if ($complianceModule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        $complianceModule->load('assessments');

        return new ComplianceModuleResource($complianceModule);
    }

    /**
     * Update the specified module.
     */
    public function update(
        UpdateComplianceModuleRequest $request,
        string $tenant,
        ComplianceCourse $complianceCourse,
        ComplianceModule $complianceModule
    ): ComplianceModuleResource {
        Gate::authorize('can-manage-training');

        // Ensure module belongs to the course
        if ($complianceModule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        $validated = $request->validated();

        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file
            if ($complianceModule->file_path) {
                Storage::disk('private')->delete($complianceModule->file_path);
            }

            $file = $request->file('file');
            $path = $file->store('compliance-modules', 'private');

            $validated['file_path'] = $path;
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['file_size'] = $file->getSize();
            $validated['mime_type'] = $file->getMimeType();
        }

        $complianceModule->update($validated);
        $complianceModule->load('assessments');

        return new ComplianceModuleResource($complianceModule);
    }

    /**
     * Remove the specified module.
     */
    public function destroy(
        string $tenant,
        ComplianceCourse $complianceCourse,
        ComplianceModule $complianceModule
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        // Ensure module belongs to the course
        if ($complianceModule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        // Delete file if exists
        if ($complianceModule->file_path) {
            Storage::disk('private')->delete($complianceModule->file_path);
        }

        $complianceModule->delete();

        return response()->json([
            'message' => 'Module deleted successfully.',
        ]);
    }

    /**
     * Reorder modules.
     */
    public function reorder(
        Request $request,
        string $tenant,
        ComplianceCourse $complianceCourse
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer', 'exists:compliance_modules,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->input('order') as $item) {
            ComplianceModule::where('id', $item['id'])
                ->where('compliance_course_id', $complianceCourse->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'Modules reordered successfully.',
        ]);
    }

    /**
     * Download module file.
     */
    public function download(
        string $tenant,
        ComplianceCourse $complianceCourse,
        ComplianceModule $complianceModule
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        Gate::authorize('can-view-training');

        // Ensure module belongs to the course
        if ($complianceModule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        if (! $complianceModule->file_path) {
            abort(404, 'No file attached to this module.');
        }

        if (! Storage::disk('private')->exists($complianceModule->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('private')->download(
            $complianceModule->file_path,
            $complianceModule->file_name
        );
    }
}
