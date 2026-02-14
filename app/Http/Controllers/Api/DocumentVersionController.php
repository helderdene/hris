<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewVersionUploadRequest;
use App\Http\Resources\DocumentVersionResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API controller for managing document versions.
 *
 * Handles uploading new versions, downloading, and previewing specific versions.
 */
class DocumentVersionController extends Controller
{
    public function __construct(
        protected DocumentStorageService $storageService
    ) {}

    /**
     * Store a new version of an existing document.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function store(NewVersionUploadRequest $request, Document $document): JsonResponse
    {
        // Check authorization based on document type
        if ($document->is_company_document) {
            Gate::authorize('can-manage-company-documents');
        } else {
            Gate::authorize('can-manage-employee-documents', $document->employee);
        }

        $validated = $request->validated();
        $file = $request->file('file');

        $result = DB::transaction(function () use ($document, $validated, $file, $tenant) {
            // Get the next version number
            $nextVersion = $document->versions()->max('version_number') + 1;

            // Determine the employee ID (null for company documents)
            $employeeId = $document->is_company_document ? null : $document->employee_id;

            // Store the file
            $fileData = $this->storageService->store($file, $tenant, $employeeId);

            // Create the new version
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersion,
                'stored_filename' => $fileData['stored_filename'],
                'file_path' => $fileData['file_path'],
                'file_size' => $fileData['file_size'],
                'mime_type' => $fileData['mime_type'],
                'version_notes' => $validated['version_notes'] ?? null,
                'uploaded_by' => auth()->id(),
            ]);

            // Update document's current file info to latest version
            $document->update([
                'stored_filename' => $fileData['stored_filename'],
                'file_path' => $fileData['file_path'],
                'file_size' => $fileData['file_size'],
                'mime_type' => $fileData['mime_type'],
            ]);

            return $version;
        });

        $result->load('uploadedBy');

        return response()->json([
            'data' => new DocumentVersionResource($result),
        ], 201);
    }

    /**
     * Download a specific version of a document.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function download(Document $document, DocumentVersion $version): StreamedResponse
    {
        // Check authorization based on document type
        if ($document->is_company_document) {
            Gate::authorize('can-view-company-documents');
        } else {
            Gate::authorize('can-view-employee-documents', $document->employee);
        }

        // Ensure version belongs to this document
        if ($version->document_id !== $document->id) {
            abort(404);
        }

        // Check if file exists
        if (! $this->storageService->fileExists($version->file_path)) {
            abort(404, 'File not found');
        }

        // Get the original filename from the document for download
        $downloadName = $document->original_filename;

        // Stream the file download
        return Storage::disk(DocumentStorageService::getDiskName())
            ->download($version->file_path, $downloadName, [
                'Content-Type' => $version->mime_type,
            ]);
    }

    /**
     * Preview a specific version of a document inline in the browser.
     *
     * This method serves the file with 'inline' content disposition,
     * allowing the browser to display PDFs and images directly.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function preview(Document $document, DocumentVersion $version): StreamedResponse
    {
        // Check authorization based on document type
        if ($document->is_company_document) {
            Gate::authorize('can-view-company-documents');
        } else {
            Gate::authorize('can-view-employee-documents', $document->employee);
        }

        // Ensure version belongs to this document
        if ($version->document_id !== $document->id) {
            abort(404);
        }

        // Check if file exists
        if (! $this->storageService->fileExists($version->file_path)) {
            abort(404, 'File not found');
        }

        // Get the original filename from the document
        $filename = $document->original_filename;

        // Stream the file with inline disposition for browser preview
        return Storage::disk(DocumentStorageService::getDiskName())
            ->response($version->file_path, $filename, [
                'Content-Type' => $version->mime_type,
                'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            ]);
    }
}
