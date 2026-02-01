<?php

/**
 * Tests for Employee Self-Service Certification API - Model and Validation Tests
 */

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationFile;
use App\Models\CertificationType;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    Storage::fake('tenant-documents');

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
});

describe('My Certification - Data Isolation', function () {
    it('employee can only access their own certifications', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        Certification::factory()->create(['employee_id' => $employee1->id]);
        Certification::factory()->create(['employee_id' => $employee1->id]);
        Certification::factory()->create(['employee_id' => $employee2->id]);

        $employee1Certs = Certification::forEmployee($employee1->id)->get();
        $employee2Certs = Certification::forEmployee($employee2->id)->get();

        expect($employee1Certs)->toHaveCount(2);
        expect($employee2Certs)->toHaveCount(1);
    });
});

describe('My Certification - Draft CRUD', function () {
    it('creates certification in draft status', function () {
        $employee = Employee::factory()->create();
        $type = CertificationType::factory()->create();

        $certification = Certification::create([
            'employee_id' => $employee->id,
            'certification_type_id' => $type->id,
            'certificate_number' => 'CERT-001',
            'issuing_body' => 'Red Cross',
            'issued_date' => '2024-01-15',
            'expiry_date' => '2026-01-15',
            'description' => 'Annual first aid certification',
            'status' => CertificationStatus::Draft,
        ]);

        expect($certification->status)->toBe(CertificationStatus::Draft);
        expect($certification->certificate_number)->toBe('CERT-001');
    });

    it('can update draft certification', function () {
        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
            'certificate_number' => 'OLD-001',
        ]);

        $certification->update(['certificate_number' => 'NEW-001']);

        expect($certification->fresh()->certificate_number)->toBe('NEW-001');
    });

    it('can delete draft certification', function () {
        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
        ]);

        $id = $certification->id;
        $certification->delete();

        expect(Certification::find($id))->toBeNull();
    });

    it('draft certification can be edited', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::Draft]);

        expect($certification->canBeEdited)->toBeTrue();
    });

    it('active certification cannot be edited', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::Active]);

        expect($certification->canBeEdited)->toBeFalse();
    });
});

describe('My Certification - Submit Workflow', function () {
    it('submits draft certification with file for approval', function () {
        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
        ]);

        // Add required file
        CertificationFile::factory()->create(['certification_id' => $certification->id]);

        $certification->submit();

        expect($certification->fresh()->status)->toBe(CertificationStatus::PendingApproval);
        expect($certification->fresh()->submitted_at)->not->toBeNull();
    });

    it('cannot submit certification without files', function () {
        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
        ]);

        // canBeSubmitted returns false when no files
        expect($certification->canBeSubmitted)->toBeFalse();
    });

    it('cannot submit non-draft certification', function () {
        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::PendingApproval,
        ]);

        CertificationFile::factory()->create(['certification_id' => $certification->id]);

        expect($certification->canBeSubmitted)->toBeFalse();
    });
});

describe('My Certification - File Management', function () {
    it('can attach files to certification', function () {
        $certification = Certification::factory()->create();

        $file = CertificationFile::factory()->create([
            'certification_id' => $certification->id,
            'original_filename' => 'certificate.pdf',
        ]);

        expect($certification->files)->toHaveCount(1);
        expect($certification->files->first()->original_filename)->toBe('certificate.pdf');
    });

    it('can attach multiple files', function () {
        $certification = Certification::factory()->create();

        CertificationFile::factory()->count(3)->create([
            'certification_id' => $certification->id,
        ]);

        expect($certification->files)->toHaveCount(3);
    });
});

describe('My Certification - Statistics Queries', function () {
    it('counts total certifications for employee', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->count(5)->create([
            'employee_id' => $employee->id,
        ]);

        expect(Certification::forEmployee($employee->id)->count())->toBe(5);
    });

    it('counts active certifications', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
        ]);

        expect(Certification::forEmployee($employee->id)->active()->count())->toBe(2);
    });

    it('counts draft certifications', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);

        expect(Certification::forEmployee($employee->id)->where('status', CertificationStatus::Draft)->count())->toBe(3);
    });

    it('counts pending approval certifications', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::PendingApproval,
        ]);
        Certification::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);

        expect(Certification::forEmployee($employee->id)->pendingApproval()->count())->toBe(1);
    });

    it('counts expiring soon certifications', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
            'expiry_date' => now()->addDays(15),
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
            'expiry_date' => now()->addDays(60),
        ]);

        expect(Certification::forEmployee($employee->id)->expiringWithin(30)->count())->toBe(1);
    });
});

describe('My Certification - Validation', function () {
    it('certification type must exist', function () {
        $employee = Employee::factory()->create();

        expect(fn () => Certification::create([
            'employee_id' => $employee->id,
            'certification_type_id' => 9999,
            'issued_date' => '2024-01-15',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('issued date before expiry date is valid', function () {
        $employee = Employee::factory()->create();
        $type = CertificationType::factory()->create();

        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'certification_type_id' => $type->id,
            'issued_date' => '2024-01-15',
            'expiry_date' => '2026-01-15',
        ]);

        expect($certification->issued_date->lt($certification->expiry_date))->toBeTrue();
    });
});
