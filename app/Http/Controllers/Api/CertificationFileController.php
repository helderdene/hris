<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadCertificationFileRequest;
use App\Http\Resources\CertificationFileResource;
use App\Models\Certification;
use App\Models\CertificationFile;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificationFileController extends Controller
{
    public function __construct(
        protected DocumentStorageService $storageService
    ) {}

    /**
     * Upload a file to a certification.
     */
    public function store(
        UploadCertificationFileRequest $request,
        string $tenant,
        Certification $certification
    ): JsonResponse {
        // Verify the user owns this certification or is HR
        if (! $this->canManageFiles($certification)) {
            return response()->json([
                'message' => 'Unauthorized to upload files to this certification.',
            ], 403);
        }

        if (! $certification->can_be_edited) {
            return response()->json([
                'message' => 'Cannot upload files to a submitted or approved certification.',
            ], 422);
        }

        $file = $request->file('file');
        $tenantSlug = tenant()?->slug ?? 'default';

        // Generate stored filename
        $extension = $file->getClientOriginalExtension();
        $storedFilename = Str::uuid().'.'.$extension;

        // Build storage path
        $path = "{$tenantSlug}/certifications/".now()->format('Y/m')."/{$storedFilename}";

        // Store the file
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        // Create the file record
        $certificationFile = CertificationFile::create([
            'certification_id' => $certification->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return (new CertificationFileResource($certificationFile))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Download a certification file.
     */
    public function download(
        string $tenant,
        Certification $certification,
        CertificationFile $file
    ): StreamedResponse|JsonResponse {
        // Verify the file belongs to this certification
        if ($file->certification_id !== $certification->id) {
            return response()->json([
                'message' => 'File not found.',
            ], 404);
        }

        // Verify access
        if (! $this->canViewFiles($certification)) {
            return response()->json([
                'message' => 'Unauthorized to access this file.',
            ], 403);
        }

        $path = $file->file_path;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json([
                'message' => 'File not found on storage.',
            ], 404);
        }

        return Storage::disk('local')->download($path, $file->original_filename);
    }

    /**
     * Preview a certification file (inline display).
     */
    public function preview(
        string $tenant,
        Certification $certification,
        CertificationFile $file
    ): StreamedResponse|JsonResponse {
        // Verify the file belongs to this certification
        if ($file->certification_id !== $certification->id) {
            return response()->json([
                'message' => 'File not found.',
            ], 404);
        }

        // Verify access
        if (! $this->canViewFiles($certification)) {
            return response()->json([
                'message' => 'Unauthorized to access this file.',
            ], 403);
        }

        $path = $file->file_path;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json([
                'message' => 'File not found on storage.',
            ], 404);
        }

        return response()->stream(
            fn () => print (Storage::disk('local')->get($path)),
            200,
            [
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => 'inline; filename="'.$file->original_filename.'"',
            ]
        );
    }

    /**
     * Delete a certification file.
     */
    public function destroy(
        string $tenant,
        Certification $certification,
        CertificationFile $file
    ): JsonResponse {
        // Verify the file belongs to this certification
        if ($file->certification_id !== $certification->id) {
            return response()->json([
                'message' => 'File not found.',
            ], 404);
        }

        // Verify the user owns this certification or is HR
        if (! $this->canManageFiles($certification)) {
            return response()->json([
                'message' => 'Unauthorized to delete files from this certification.',
            ], 403);
        }

        if (! $certification->can_be_edited) {
            return response()->json([
                'message' => 'Cannot delete files from a submitted or approved certification.',
            ], 422);
        }

        // Delete from storage
        if (Storage::disk('local')->exists($file->file_path)) {
            Storage::disk('local')->delete($file->file_path);
        }

        $file->delete();

        return response()->json([
            'message' => 'File deleted successfully.',
        ]);
    }

    /**
     * Check if the current user can manage files for this certification.
     */
    protected function canManageFiles(Certification $certification): bool
    {
        $user = Auth::user();
        $employee = $user?->employee;

        // Owner can manage their own certification files
        if ($employee && $certification->employee_id === $employee->id) {
            return true;
        }

        // HR can manage all certification files
        return $user?->can('can-manage-organization') ?? false;
    }

    /**
     * Check if the current user can view files for this certification.
     */
    protected function canViewFiles(Certification $certification): bool
    {
        $user = Auth::user();
        $employee = $user?->employee;

        // Owner can view their own certification files
        if ($employee && $certification->employee_id === $employee->id) {
            return true;
        }

        // HR can view all certification files
        return $user?->can('can-manage-organization') ?? false;
    }
}
