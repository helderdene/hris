<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseMaterialType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseMaterialRequest;
use App\Http\Resources\CourseMaterialResource;
use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseMaterialController extends Controller
{
    /**
     * The storage disk name for course materials.
     */
    private const DISK = 'tenant-documents';

    /**
     * Display a listing of materials for a course.
     */
    public function index(string $tenant, Course $course): AnonymousResourceCollection
    {
        // For employees without manage permission, only show materials from published courses
        if (! Gate::allows('can-manage-training') && ! $course->isPublished()) {
            abort(403, 'This course is not available.');
        }

        Gate::authorize('can-view-training');

        $materials = $course->materials()
            ->with('uploader')
            ->ordered()
            ->get();

        return CourseMaterialResource::collection($materials);
    }

    /**
     * Store a newly created material.
     */
    public function store(
        StoreCourseMaterialRequest $request,
        string $tenant,
        Course $course
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $materialType = CourseMaterialType::from($validated['material_type']);

        $materialData = [
            'course_id' => $course->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'material_type' => $materialType,
            'external_url' => $validated['external_url'] ?? null,
            'sort_order' => $course->materials()->max('sort_order') + 1,
            'uploaded_by' => auth()->user()->employee?->id,
        ];

        // Handle file upload for non-link types
        if ($materialType->requiresFile() && $request->hasFile('file')) {
            $file = $request->file('file');
            $storedFilename = $this->generateUniqueFilename($file->getClientOriginalName());
            $path = $this->generateStoragePath($tenant, $course->id);
            $fullPath = $path.'/'.$storedFilename;

            Storage::disk(self::DISK)->putFileAs($path, $file, $storedFilename);

            $materialData['file_name'] = $file->getClientOriginalName();
            $materialData['file_path'] = $fullPath;
            $materialData['file_size'] = $file->getSize();
            $materialData['mime_type'] = $file->getMimeType();
        }

        $material = CourseMaterial::create($materialData);
        $material->load('uploader');

        return (new CourseMaterialResource($material))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified material.
     */
    public function update(
        Request $request,
        string $tenant,
        Course $course,
        CourseMaterial $material
    ): CourseMaterialResource {
        Gate::authorize('can-manage-training');

        $this->ensureMaterialBelongsToCourse($material, $course);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'external_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $material->update($validated);
        $material->load('uploader');

        return new CourseMaterialResource($material);
    }

    /**
     * Remove the specified material.
     */
    public function destroy(
        string $tenant,
        Course $course,
        CourseMaterial $material
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $this->ensureMaterialBelongsToCourse($material, $course);

        // Delete the file if it exists
        if ($material->hasFile() && $material->file_path) {
            Storage::disk(self::DISK)->delete($material->file_path);
        }

        $material->delete();

        return response()->json([
            'message' => 'Material deleted successfully.',
        ]);
    }

    /**
     * Reorder materials within a course.
     */
    public function reorder(
        Request $request,
        string $tenant,
        Course $course
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $validated = $request->validate([
            'material_ids' => ['required', 'array'],
            'material_ids.*' => ['integer'],
        ]);

        $materialIds = $validated['material_ids'];

        // Verify all materials belong to this course
        $courseMaterialIds = $course->materials()->pluck('id')->toArray();
        foreach ($materialIds as $id) {
            if (! in_array($id, $courseMaterialIds)) {
                return response()->json([
                    'message' => 'One or more materials do not belong to this course.',
                ], 422);
            }
        }

        // Update sort order
        foreach ($materialIds as $index => $id) {
            CourseMaterial::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'message' => 'Materials reordered successfully.',
        ]);
    }

    /**
     * Download a material file.
     */
    public function download(string $tenant, CourseMaterial $material): StreamedResponse|JsonResponse
    {
        // For employees without manage permission, only allow download from published courses
        if (! Gate::allows('can-manage-training') && ! $material->course->isPublished()) {
            abort(403, 'This course is not available.');
        }

        Gate::authorize('can-view-training');

        if (! $material->hasFile() || ! $material->file_path) {
            return response()->json([
                'message' => 'This material does not have a downloadable file.',
            ], 404);
        }

        if (! Storage::disk(self::DISK)->exists($material->file_path)) {
            return response()->json([
                'message' => 'The file could not be found.',
            ], 404);
        }

        return Storage::disk(self::DISK)->download(
            $material->file_path,
            $material->file_name,
            ['Content-Type' => $material->mime_type]
        );
    }

    /**
     * Generate the storage path for course materials.
     */
    private function generateStoragePath(string $tenantSlug, int $courseId): string
    {
        return $tenantSlug.'/course-materials/'.$courseId;
    }

    /**
     * Generate a unique filename while preserving the original extension.
     */
    private function generateUniqueFilename(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueId = Str::uuid()->toString();

        if (empty($extension)) {
            return $uniqueId;
        }

        return $uniqueId.'.'.$extension;
    }

    /**
     * Ensure the material belongs to the specified course.
     */
    private function ensureMaterialBelongsToCourse(CourseMaterial $material, Course $course): void
    {
        if ($material->course_id !== $course->id) {
            abort(404, 'Material not found for this course.');
        }
    }
}
