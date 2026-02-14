<?php

namespace App\Http\Controllers\Api;

use App\Events\ProfilePhotoUploaded;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentUploadRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Employee;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for managing employee documents.
 *
 * Handles CRUD operations for documents attached to employee profiles.
 * Follows the pattern from EmployeeCompensationController.
 */
class EmployeeDocumentController extends Controller
{
    public function __construct(
        protected DocumentStorageService $storageService
    ) {}

    /**
     * Display a listing of the employee's documents.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function index(Employee $employee): JsonResponse
    {
        Gate::authorize('can-view-employee-documents', $employee);

        $query = $employee->documents()
            ->with(['category', 'versions' => function ($query) {
                $query->orderByDesc('version_number')->limit(1);
            }])
            ->orderBy('created_at', 'desc');

        // Filter by category if provided
        if (request()->has('category_id')) {
            $query->where('document_category_id', request('category_id'));
        }

        // Paginate results (20 per page as per spec)
        $documents = $query->paginate(20);

        return response()->json([
            'data' => DocumentResource::collection($documents),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    /**
     * Store a newly uploaded document for the employee.
     *
     * Creates both the Document record and an initial DocumentVersion.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function store(DocumentUploadRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('can-manage-employee-documents', $employee);

        $validated = $request->validated();
        $file = $request->file('file');

        $tenantSlug = tenant()->slug;

        $result = DB::transaction(function () use ($employee, $validated, $file, $tenantSlug) {
            // Store the file
            $fileData = $this->storageService->store($file, $tenantSlug, $employee->id);

            // Create the document record
            $document = Document::create([
                'employee_id' => $employee->id,
                'document_category_id' => $validated['document_category_id'],
                'name' => $validated['name'],
                'original_filename' => $fileData['original_filename'],
                'stored_filename' => $fileData['stored_filename'],
                'file_path' => $fileData['file_path'],
                'mime_type' => $fileData['mime_type'],
                'file_size' => $fileData['file_size'],
                'is_company_document' => false,
            ]);

            // Create the initial version
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'stored_filename' => $fileData['stored_filename'],
                'file_path' => $fileData['file_path'],
                'file_size' => $fileData['file_size'],
                'mime_type' => $fileData['mime_type'],
                'version_notes' => $validated['version_notes'] ?? null,
                'uploaded_by' => auth()->id(),
            ]);

            return $document;
        });

        // Load relationships for response (uploadedBy resolved in resource from platform DB)
        $result->load(['category', 'versions']);

        // Dispatch event if this is a profile photo upload
        $this->dispatchProfilePhotoEventIfApplicable($employee, $result);

        return response()->json([
            'data' => new DocumentResource($result),
        ], 201);
    }

    /**
     * Dispatch ProfilePhotoUploaded event if the document is a profile photo.
     */
    protected function dispatchProfilePhotoEventIfApplicable(Employee $employee, Document $document): void
    {
        $category = $document->category;

        if ($category === null) {
            return;
        }

        if ($category->name === 'Profile Photo') {
            ProfilePhotoUploaded::dispatch($employee, $document);
        }
    }

    /**
     * Display the specified document with all versions.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function show(Employee $employee, Document $document): JsonResponse
    {
        Gate::authorize('can-view-employee-documents', $employee);

        // Ensure document belongs to this employee
        if ($document->employee_id !== $employee->id) {
            abort(404);
        }

        $document->load(['category', 'versions']);

        return response()->json([
            'data' => new DocumentResource($document),
        ]);
    }

    /**
     * Remove the specified document (soft delete).
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function destroy(Employee $employee, Document $document): JsonResponse
    {
        Gate::authorize('can-manage-employee-documents', $employee);

        // Ensure document belongs to this employee
        if ($document->employee_id !== $employee->id) {
            abort(404);
        }

        $document->delete();

        return response()->json(null, 204);
    }
}
