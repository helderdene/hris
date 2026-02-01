<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Employee;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('DocumentCategory Model', function () {
    it('can be created with required fields', function () {
        $category = DocumentCategory::factory()->create([
            'name' => 'Contracts',
            'description' => 'Employment contracts and agreements',
            'is_predefined' => true,
        ]);

        expect($category)->toBeInstanceOf(DocumentCategory::class);
        expect($category->name)->toBe('Contracts');
        expect($category->description)->toBe('Employment contracts and agreements');
        expect($category->is_predefined)->toBeTrue();
    });

    it('extends TenantModel for multi-tenant database isolation', function () {
        $category = new DocumentCategory;

        expect($category)->toBeInstanceOf(TenantModel::class);
    });

    it('has many documents relationship', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Certifications']);

        $document1 = Document::factory()->create([
            'document_category_id' => $category->id,
            'name' => 'Certificate 1',
        ]);

        $document2 = Document::factory()->create([
            'document_category_id' => $category->id,
            'name' => 'Certificate 2',
        ]);

        expect($category->documents)->toHaveCount(2);
        expect($category->documents->first()->id)->toBe($document1->id);
    });
});

describe('Document Model', function () {
    it('can be created with required fields', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Personal Documents']);
        $employee = Employee::factory()->create();

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Employment Contract',
            'original_filename' => 'contract.pdf',
            'stored_filename' => 'abc123_contract.pdf',
            'file_path' => 'tenant-slug/documents/1/abc123_contract.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024000,
            'is_company_document' => false,
        ]);

        expect($document)->toBeInstanceOf(Document::class);
        expect($document->name)->toBe('Employment Contract');
        expect($document->original_filename)->toBe('contract.pdf');
        expect($document->file_size)->toBe(1024000);
        expect($document->is_company_document)->toBeFalse();
    });

    it('extends TenantModel for multi-tenant database isolation', function () {
        $document = new Document;

        expect($document)->toBeInstanceOf(TenantModel::class);
    });

    it('belongs to DocumentCategory', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Company Memos']);
        $document = Document::factory()->create([
            'document_category_id' => $category->id,
        ]);

        expect($document->category)->toBeInstanceOf(DocumentCategory::class);
        expect($document->category->id)->toBe($category->id);
        expect($document->category->name)->toBe('Company Memos');
    });

    it('belongs to Employee (nullable)', function () {
        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);

        // Document with employee
        $employeeDocument = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'is_company_document' => false,
        ]);

        expect($employeeDocument->employee)->toBeInstanceOf(Employee::class);
        expect($employeeDocument->employee->id)->toBe($employee->id);

        // Company document without employee
        $companyDocument = Document::factory()->create([
            'employee_id' => null,
            'document_category_id' => $category->id,
            'is_company_document' => true,
        ]);

        expect($companyDocument->employee)->toBeNull();
        expect($companyDocument->is_company_document)->toBeTrue();
    });

    it('supports soft deletes', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);
        $document = Document::factory()->create([
            'document_category_id' => $category->id,
            'name' => 'To be deleted',
        ]);

        $documentId = $document->id;

        $document->delete();

        // Should not be found with normal query
        expect(Document::find($documentId))->toBeNull();

        // Should be found with trashed
        $trashedDocument = Document::withTrashed()->find($documentId);
        expect($trashedDocument)->not->toBeNull();
        expect($trashedDocument->deleted_at)->not->toBeNull();

        // Can be restored
        $trashedDocument->restore();
        expect(Document::find($documentId))->not->toBeNull();
    });

    it('has many document versions', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);
        $document = Document::factory()->create([
            'document_category_id' => $category->id,
        ]);

        $user = User::factory()->create();

        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $user->id,
        ]);

        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'uploaded_by' => $user->id,
        ]);

        expect($document->versions)->toHaveCount(2);
    });
});

describe('DocumentVersion Model', function () {
    it('can be created with required fields', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);
        $document = Document::factory()->create([
            'document_category_id' => $category->id,
        ]);
        $user = User::factory()->create();

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'stored_filename' => 'abc123_contract_v1.pdf',
            'file_path' => 'tenant-slug/documents/1/abc123_contract_v1.pdf',
            'file_size' => 1024000,
            'mime_type' => 'application/pdf',
            'version_notes' => 'Initial version',
            'uploaded_by' => $user->id,
        ]);

        expect($version)->toBeInstanceOf(DocumentVersion::class);
        expect($version->version_number)->toBe(1);
        expect($version->version_notes)->toBe('Initial version');
        expect($version->file_size)->toBe(1024000);
    });

    it('extends TenantModel for multi-tenant database isolation', function () {
        $version = new DocumentVersion;

        expect($version)->toBeInstanceOf(TenantModel::class);
    });

    it('belongs to Document', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);
        $document = Document::factory()->create([
            'document_category_id' => $category->id,
            'name' => 'Test Document',
        ]);
        $user = User::factory()->create();

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
        ]);

        expect($version->document)->toBeInstanceOf(Document::class);
        expect($version->document->id)->toBe($document->id);
        expect($version->document->name)->toBe('Test Document');
    });

    it('belongs to User (uploaded_by)', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);
        $document = Document::factory()->create([
            'document_category_id' => $category->id,
        ]);
        $user = User::factory()->create(['name' => 'John Uploader']);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
        ]);

        expect($version->uploadedBy)->toBeInstanceOf(User::class);
        expect($version->uploadedBy->id)->toBe($user->id);
        expect($version->uploadedBy->name)->toBe('John Uploader');
    });
});

describe('Employee hasMany Documents relationship', function () {
    it('returns all documents belonging to an employee', function () {
        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);

        Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Contract 1',
        ]);

        Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Contract 2',
        ]);

        // Create document for different employee
        $otherEmployee = Employee::factory()->create();
        Document::factory()->create([
            'employee_id' => $otherEmployee->id,
            'document_category_id' => $category->id,
            'name' => 'Other Contract',
        ]);

        expect($employee->documents)->toHaveCount(2);
        $employee->documents->each(function ($document) use ($employee) {
            expect($document->employee_id)->toBe($employee->id);
        });
    });
});

describe('Version number auto-increment logic', function () {
    it('can manually track version numbers for a document', function () {
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);
        $document = Document::factory()->create([
            'document_category_id' => $category->id,
        ]);
        $user = User::factory()->create();

        // Create first version
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $user->id,
        ]);

        // Get the next version number
        $latestVersion = DocumentVersion::where('document_id', $document->id)
            ->max('version_number');
        $nextVersion = $latestVersion + 1;

        // Create second version
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => $nextVersion,
            'uploaded_by' => $user->id,
        ]);

        // Create third version
        $latestVersion = DocumentVersion::where('document_id', $document->id)
            ->max('version_number');
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => $latestVersion + 1,
            'uploaded_by' => $user->id,
        ]);

        $versions = $document->versions()->orderBy('version_number')->get();

        expect($versions)->toHaveCount(3);
        expect($versions[0]->version_number)->toBe(1);
        expect($versions[1]->version_number)->toBe(2);
        expect($versions[2]->version_number)->toBe(3);
    });
});
