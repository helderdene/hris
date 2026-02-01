<?php

/**
 * Tests for Document Storage Configuration and Service
 *
 * Tests the tenant-documents disk configuration, DocumentStorageService functionality,
 * and DocumentUploadRequest validation.
 */

use App\Http\Requests\DocumentUploadRequest;
use App\Models\DocumentCategory;
use App\Services\DocumentStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Use fake storage for tests
    Storage::fake('tenant-documents');
});

describe('Tenant Documents Disk Configuration', function () {
    it('has tenant-documents disk configured', function () {
        // Get the disk configuration
        $disks = config('filesystems.disks');

        expect($disks)->toHaveKey('tenant-documents');
        expect($disks['tenant-documents']['driver'])->toBe('local');
        expect($disks['tenant-documents']['root'])->toBe(storage_path('app/private/tenants'));
    });

    it('does not expose public URL for tenant-documents disk', function () {
        $diskConfig = config('filesystems.disks.tenant-documents');

        // The disk should not have a URL configuration (private storage)
        expect($diskConfig)->not->toHaveKey('url');
        expect($diskConfig)->not->toHaveKey('visibility');
    });
});

describe('DocumentStorageService Path Generation', function () {
    it('generates correct path for employee documents', function () {
        $service = new DocumentStorageService;

        $path = $service->generatePath('test-tenant', 123);

        expect($path)->toBe('test-tenant/documents/123');
    });

    it('generates correct path for company documents', function () {
        $service = new DocumentStorageService;

        $path = $service->generatePath('acme-corp', null);

        expect($path)->toBe('acme-corp/documents/company');
    });

    it('follows private storage path structure', function () {
        $service = new DocumentStorageService;

        // Verify path structure matches spec: {tenant_slug}/documents/{employee_id|company}/
        $employeePath = $service->generatePath('my-tenant', 42);
        $companyPath = $service->generatePath('my-tenant', null);

        expect($employeePath)->toMatch('/^[a-z0-9-]+\/documents\/\d+$/');
        expect($companyPath)->toMatch('/^[a-z0-9-]+\/documents\/company$/');
    });
});

describe('DocumentStorageService File Upload', function () {
    it('stores file to correct tenant path', function () {
        $service = new DocumentStorageService;

        $file = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');

        $result = $service->store($file, 'test-tenant', 123);

        expect($result)->toHaveKeys([
            'stored_filename',
            'file_path',
            'file_size',
            'mime_type',
            'original_filename',
        ]);

        // Verify file path starts with correct tenant path
        expect($result['file_path'])->toStartWith('test-tenant/documents/123/');
        expect($result['original_filename'])->toBe('test-document.pdf');
        expect($result['mime_type'])->toBe('application/pdf');

        // Verify file exists in storage
        Storage::disk('tenant-documents')->assertExists($result['file_path']);
    });

    it('stores company document to company path', function () {
        $service = new DocumentStorageService;

        $file = UploadedFile::fake()->create('company-policy.pdf', 500, 'application/pdf');

        $result = $service->store($file, 'acme-corp', null);

        expect($result['file_path'])->toStartWith('acme-corp/documents/company/');
        Storage::disk('tenant-documents')->assertExists($result['file_path']);
    });
});

describe('DocumentStorageService Unique Filename Generation', function () {
    it('generates unique filenames', function () {
        $service = new DocumentStorageService;

        $filename1 = $service->generateUniqueFilename('document.pdf');
        $filename2 = $service->generateUniqueFilename('document.pdf');

        // Filenames should be unique
        expect($filename1)->not->toBe($filename2);

        // Both should have .pdf extension
        expect($filename1)->toEndWith('.pdf');
        expect($filename2)->toEndWith('.pdf');
    });

    it('preserves file extension in unique filename', function () {
        $service = new DocumentStorageService;

        $pdfFilename = $service->generateUniqueFilename('report.pdf');
        $docxFilename = $service->generateUniqueFilename('document.docx');
        $jpgFilename = $service->generateUniqueFilename('photo.jpg');

        expect($pdfFilename)->toEndWith('.pdf');
        expect($docxFilename)->toEndWith('.docx');
        expect($jpgFilename)->toEndWith('.jpg');
    });

    it('handles files without extension', function () {
        $service = new DocumentStorageService;

        $filename = $service->generateUniqueFilename('noextension');

        // Should be a UUID without extension
        expect($filename)->toMatch('/^[a-f0-9-]{36}$/');
    });
});

describe('DocumentStorageService MIME Type Validation', function () {
    it('validates allowed MIME types', function () {
        $service = new DocumentStorageService;

        // PDF
        $pdfFile = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        expect($service->validateMimeType($pdfFile))->toBeTrue();

        // DOCX
        $docxFile = UploadedFile::fake()->create('doc.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        expect($service->validateMimeType($docxFile))->toBeTrue();

        // DOC
        $docFile = UploadedFile::fake()->create('doc.doc', 100, 'application/msword');
        expect($service->validateMimeType($docFile))->toBeTrue();

        // JPEG
        $jpegFile = UploadedFile::fake()->image('photo.jpg');
        expect($service->validateMimeType($jpegFile))->toBeTrue();

        // PNG
        $pngFile = UploadedFile::fake()->image('photo.png');
        expect($service->validateMimeType($pngFile))->toBeTrue();

        // XLS
        $xlsFile = UploadedFile::fake()->create('sheet.xls', 100, 'application/vnd.ms-excel');
        expect($service->validateMimeType($xlsFile))->toBeTrue();

        // XLSX
        $xlsxFile = UploadedFile::fake()->create('sheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        expect($service->validateMimeType($xlsxFile))->toBeTrue();
    });

    it('rejects invalid MIME types', function () {
        $service = new DocumentStorageService;

        // Executable
        $exeFile = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');
        expect($service->validateMimeType($exeFile))->toBeFalse();

        // PHP
        $phpFile = UploadedFile::fake()->create('script.php', 100, 'text/x-php');
        expect($service->validateMimeType($phpFile))->toBeFalse();

        // ZIP
        $zipFile = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');
        expect($service->validateMimeType($zipFile))->toBeFalse();

        // HTML
        $htmlFile = UploadedFile::fake()->create('page.html', 100, 'text/html');
        expect($service->validateMimeType($htmlFile))->toBeFalse();
    });
});

describe('DocumentUploadRequest Validation', function () {
    it('validates file size limit of 10MB', function () {
        // Create a category first
        $category = DocumentCategory::factory()->create();

        $request = new DocumentUploadRequest;

        // File under 10MB should pass
        $validFile = UploadedFile::fake()->create('small.pdf', 5120, 'application/pdf'); // 5MB
        $validValidator = Validator::make(
            [
                'file' => $validFile,
                'document_category_id' => $category->id,
                'name' => 'Test Document',
            ],
            $request->rules(),
            $request->messages()
        );
        expect($validValidator->fails())->toBeFalse();

        // File over 10MB should fail
        $invalidFile = UploadedFile::fake()->create('large.pdf', 15360, 'application/pdf'); // 15MB
        $invalidValidator = Validator::make(
            [
                'file' => $invalidFile,
                'document_category_id' => $category->id,
                'name' => 'Test Document',
            ],
            $request->rules(),
            $request->messages()
        );
        expect($invalidValidator->fails())->toBeTrue();
        expect($invalidValidator->errors()->has('file'))->toBeTrue();
    });

    it('validates required fields', function () {
        $request = new DocumentUploadRequest;

        $validator = Validator::make([], $request->rules(), $request->messages());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('file'))->toBeTrue();
        expect($validator->errors()->has('document_category_id'))->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
    });

    it('validates document_category_id exists', function () {
        $request = new DocumentUploadRequest;

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $validator = Validator::make(
            [
                'file' => $file,
                'document_category_id' => 99999, // Non-existent category
                'name' => 'Test Document',
            ],
            $request->rules(),
            $request->messages()
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('document_category_id'))->toBeTrue();
    });

    it('validates name max length of 255 characters', function () {
        $category = DocumentCategory::factory()->create();
        $request = new DocumentUploadRequest;

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        // Valid name (255 chars)
        $validValidator = Validator::make(
            [
                'file' => $file,
                'document_category_id' => $category->id,
                'name' => str_repeat('a', 255),
            ],
            $request->rules(),
            $request->messages()
        );
        expect($validValidator->fails())->toBeFalse();

        // Invalid name (256 chars)
        $invalidValidator = Validator::make(
            [
                'file' => $file,
                'document_category_id' => $category->id,
                'name' => str_repeat('a', 256),
            ],
            $request->rules(),
            $request->messages()
        );
        expect($invalidValidator->fails())->toBeTrue();
        expect($invalidValidator->errors()->has('name'))->toBeTrue();
    });

    it('validates version_notes max length of 1000 characters', function () {
        $category = DocumentCategory::factory()->create();
        $request = new DocumentUploadRequest;

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        // Valid notes (1000 chars)
        $validValidator = Validator::make(
            [
                'file' => $file,
                'document_category_id' => $category->id,
                'name' => 'Test Document',
                'version_notes' => str_repeat('a', 1000),
            ],
            $request->rules(),
            $request->messages()
        );
        expect($validValidator->fails())->toBeFalse();

        // Invalid notes (1001 chars)
        $invalidValidator = Validator::make(
            [
                'file' => $file,
                'document_category_id' => $category->id,
                'name' => 'Test Document',
                'version_notes' => str_repeat('a', 1001),
            ],
            $request->rules(),
            $request->messages()
        );
        expect($invalidValidator->fails())->toBeTrue();
        expect($invalidValidator->errors()->has('version_notes'))->toBeTrue();
    });

    it('allows nullable version_notes', function () {
        $category = DocumentCategory::factory()->create();
        $request = new DocumentUploadRequest;

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $validator = Validator::make(
            [
                'file' => $file,
                'document_category_id' => $category->id,
                'name' => 'Test Document',
                // version_notes not provided
            ],
            $request->rules(),
            $request->messages()
        );

        expect($validator->fails())->toBeFalse();
    });
});

describe('DocumentStorageService File Operations', function () {
    it('retrieves stored file contents', function () {
        $service = new DocumentStorageService;

        // Create and store a file
        $content = 'Test file content';
        $path = 'test-tenant/documents/1/test-file.txt';
        Storage::disk('tenant-documents')->put($path, $content);

        $retrieved = $service->getFile($path);

        expect($retrieved)->toBe($content);
    });

    it('returns null for non-existent file', function () {
        $service = new DocumentStorageService;

        $result = $service->getFile('non-existent/path/file.pdf');

        expect($result)->toBeNull();
    });

    it('deletes file from storage', function () {
        $service = new DocumentStorageService;

        // Create a file
        $path = 'test-tenant/documents/1/to-delete.pdf';
        Storage::disk('tenant-documents')->put($path, 'content');

        // Verify it exists
        expect($service->fileExists($path))->toBeTrue();

        // Delete it
        $result = $service->deleteFile($path);

        expect($result)->toBeTrue();
        expect($service->fileExists($path))->toBeFalse();
    });

    it('returns false when deleting non-existent file', function () {
        $service = new DocumentStorageService;

        $result = $service->deleteFile('non-existent/file.pdf');

        expect($result)->toBeFalse();
    });
});
