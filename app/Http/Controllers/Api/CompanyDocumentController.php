<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentUploadRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for managing company-wide documents.
 *
 * Handles CRUD operations for documents not tied to any specific employee.
 * Follows the pattern from EmployeeDocumentController.
 */
class CompanyDocumentController extends Controller
{
    public function __construct(
        protected DocumentStorageService $storageService
    ) {}

    /**
     * Display a listing of company documents.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function index(string $tenant): JsonResponse
    {
        Gate::authorize('can-view-company-documents');

        $query = Document::where('is_company_document', true)
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
     * Store a newly uploaded company document.
     *
     * Creates both the Document record and an initial DocumentVersion.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function store(DocumentUploadRequest $request, string $tenant): JsonResponse
    {
        Gate::authorize('can-manage-company-documents');

        $validated = $request->validated();
        $file = $request->file('file');

        $result = DB::transaction(function () use ($validated, $file, $tenant) {
            // Store the file with employeeId = null for company documents
            $fileData = $this->storageService->store($file, $tenant, null);

            // Create the document record
            $document = Document::create([
                'employee_id' => null,
                'document_category_id' => $validated['document_category_id'],
                'name' => $validated['name'],
                'original_filename' => $fileData['original_filename'],
                'stored_filename' => $fileData['stored_filename'],
                'file_path' => $fileData['file_path'],
                'mime_type' => $fileData['mime_type'],
                'file_size' => $fileData['file_size'],
                'is_company_document' => true,
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

        return response()->json([
            'data' => new DocumentResource($result),
        ], 201);
    }

    /**
     * Display the specified company document with all versions.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function show(string $tenant, Document $document): JsonResponse
    {
        Gate::authorize('can-view-company-documents');

        // Ensure this is a company document
        if (! $document->is_company_document) {
            abort(404);
        }

        $document->load(['category', 'versions']);

        return response()->json([
            'data' => new DocumentResource($document),
        ]);
    }

    /**
     * Remove the specified company document (soft delete).
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function destroy(string $tenant, Document $document): JsonResponse
    {
        Gate::authorize('can-manage-company-documents');

        // Ensure this is a company document
        if (! $document->is_company_document) {
            abort(404);
        }

        $document->delete();

        return response()->json(null, 204);
    }
}
