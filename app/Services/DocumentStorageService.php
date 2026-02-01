<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for managing document file storage operations.
 *
 * Handles file storage, retrieval, and deletion for tenant documents.
 * Files are stored in private storage under tenant-specific paths following
 * the pattern: {tenant_slug}/documents/{employee_id|company}/
 */
class DocumentStorageService
{
    /**
     * The storage disk name for tenant documents.
     */
    protected const DISK = 'tenant-documents';

    /**
     * Maximum allowed file size in bytes (10MB).
     */
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Allowed MIME types for document uploads.
     *
     * @var array<string>
     */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Store an uploaded file in the tenant documents storage.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  string  $tenantSlug  The tenant's slug identifier
     * @param  int|null  $employeeId  The employee ID (null for company documents)
     * @return array{stored_filename: string, file_path: string, file_size: int, mime_type: string, original_filename: string}
     */
    public function store(UploadedFile $file, string $tenantSlug, ?int $employeeId = null): array
    {
        $path = $this->generatePath($tenantSlug, $employeeId);
        $storedFilename = $this->generateUniqueFilename($file->getClientOriginalName());
        $fullPath = $path.'/'.$storedFilename;

        Storage::disk(self::DISK)->putFileAs($path, $file, $storedFilename);

        return [
            'stored_filename' => $storedFilename,
            'file_path' => $fullPath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_filename' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Generate the storage path for a document.
     *
     * @param  string  $tenantSlug  The tenant's slug identifier
     * @param  int|null  $employeeId  The employee ID (null for company documents)
     * @return string The path relative to the storage disk root
     */
    public function generatePath(string $tenantSlug, ?int $employeeId = null): string
    {
        $folder = $employeeId !== null ? (string) $employeeId : 'company';

        return $tenantSlug.'/documents/'.$folder;
    }

    /**
     * Generate a unique filename while preserving the original extension.
     *
     * @param  string  $originalName  The original filename
     * @return string The unique filename
     */
    public function generateUniqueFilename(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueId = Str::uuid()->toString();

        if (empty($extension)) {
            return $uniqueId;
        }

        return $uniqueId.'.'.$extension;
    }

    /**
     * Get the contents of a file from storage.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return string|null The file contents or null if not found
     */
    public function getFile(string $path): ?string
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->get($path);
    }

    /**
     * Delete a file from storage.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return bool True if deleted successfully, false otherwise
     */
    public function deleteFile(string $path): bool
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            return false;
        }

        return Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Validate that a file's MIME type is in the allowed list.
     *
     * @param  UploadedFile  $file  The uploaded file to validate
     * @return bool True if the MIME type is allowed, false otherwise
     */
    public function validateMimeType(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();

        return in_array($mimeType, self::ALLOWED_MIME_TYPES, true);
    }

    /**
     * Get the full filesystem path for a stored file.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return string The full filesystem path
     */
    public function getFullPath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    /**
     * Check if a file exists in storage.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return bool True if the file exists, false otherwise
     */
    public function fileExists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Get the storage disk name.
     *
     * @return string The disk name
     */
    public static function getDiskName(): string
    {
        return self::DISK;
    }
}
