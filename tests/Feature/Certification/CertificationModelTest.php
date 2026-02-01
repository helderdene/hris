<?php

/**
 * Tests for Certification Models, Enums, and Status Transitions
 */

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationFile;
use App\Models\CertificationType;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
});

describe('CertificationStatus Enum', function () {
    it('has correct label for each status', function () {
        expect(CertificationStatus::Draft->label())->toBe('Draft');
        expect(CertificationStatus::PendingApproval->label())->toBe('Pending Approval');
        expect(CertificationStatus::Active->label())->toBe('Active');
        expect(CertificationStatus::Expired->label())->toBe('Expired');
        expect(CertificationStatus::Revoked->label())->toBe('Revoked');
    });

    it('has correct color for each status', function () {
        expect(CertificationStatus::Draft->color())->toBe('slate');
        expect(CertificationStatus::PendingApproval->color())->toBe('amber');
        expect(CertificationStatus::Active->color())->toBe('green');
        expect(CertificationStatus::Expired->color())->toBe('red');
        expect(CertificationStatus::Revoked->color())->toBe('slate');
    });

    it('identifies final statuses correctly', function () {
        expect(CertificationStatus::Draft->isFinal())->toBeFalse();
        expect(CertificationStatus::PendingApproval->isFinal())->toBeFalse();
        expect(CertificationStatus::Active->isFinal())->toBeFalse();
        expect(CertificationStatus::Expired->isFinal())->toBeTrue();
        expect(CertificationStatus::Revoked->isFinal())->toBeTrue();
    });

    it('identifies editable statuses correctly', function () {
        expect(CertificationStatus::Draft->canBeEdited())->toBeTrue();
        expect(CertificationStatus::PendingApproval->canBeEdited())->toBeFalse();
        expect(CertificationStatus::Active->canBeEdited())->toBeFalse();
        expect(CertificationStatus::Expired->canBeEdited())->toBeFalse();
        expect(CertificationStatus::Revoked->canBeEdited())->toBeFalse();
    });

    it('identifies submittable statuses correctly', function () {
        expect(CertificationStatus::Draft->canBeSubmitted())->toBeTrue();
        expect(CertificationStatus::PendingApproval->canBeSubmitted())->toBeFalse();
        expect(CertificationStatus::Active->canBeSubmitted())->toBeFalse();
    });

    it('returns allowed transitions for draft status', function () {
        $transitions = CertificationStatus::Draft->allowedTransitions();
        expect($transitions)->toContain(CertificationStatus::PendingApproval);
    });

    it('returns allowed transitions for pending approval status', function () {
        $transitions = CertificationStatus::PendingApproval->allowedTransitions();
        expect($transitions)->toContain(CertificationStatus::Active);
        expect($transitions)->toContain(CertificationStatus::Draft);
    });

    it('returns allowed transitions for active status', function () {
        $transitions = CertificationStatus::Active->allowedTransitions();
        expect($transitions)->toContain(CertificationStatus::Expired);
        expect($transitions)->toContain(CertificationStatus::Revoked);
    });

    it('returns no transitions for final statuses', function () {
        expect(CertificationStatus::Expired->allowedTransitions())->toBeEmpty();
        expect(CertificationStatus::Revoked->allowedTransitions())->toBeEmpty();
    });
});

describe('CertificationType Model', function () {
    it('can be created with factory', function () {
        $type = CertificationType::factory()->create([
            'name' => 'PRC License',
            'validity_period_months' => 36,
        ]);

        expect($type)->toBeInstanceOf(CertificationType::class);
        expect($type->name)->toBe('PRC License');
        expect($type->validity_period_months)->toBe(36);
    });

    it('has certifications relationship', function () {
        $type = CertificationType::factory()->create();
        $employee = Employee::factory()->create();

        Certification::factory()->create([
            'certification_type_id' => $type->id,
            'employee_id' => $employee->id,
        ]);

        expect($type->certifications)->toHaveCount(1);
    });

    it('filters active types with scope', function () {
        CertificationType::factory()->create(['is_active' => true]);
        CertificationType::factory()->create(['is_active' => false]);

        expect(CertificationType::active()->count())->toBe(1);
    });

    it('filters mandatory types with scope', function () {
        CertificationType::factory()->create(['is_mandatory' => true]);
        CertificationType::factory()->create(['is_mandatory' => false]);

        expect(CertificationType::mandatory()->count())->toBe(1);
    });
});

describe('Certification Model', function () {
    it('can be created with factory', function () {
        $employee = Employee::factory()->create();
        $type = CertificationType::factory()->create();

        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'certification_type_id' => $type->id,
            'status' => CertificationStatus::Draft,
        ]);

        expect($certification)->toBeInstanceOf(Certification::class);
        expect($certification->status)->toBe(CertificationStatus::Draft);
    });

    it('has employee relationship', function () {
        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create(['employee_id' => $employee->id]);

        expect($certification->employee->id)->toBe($employee->id);
    });

    it('has certification type relationship', function () {
        $type = CertificationType::factory()->create(['name' => 'CPR Certification']);
        $certification = Certification::factory()->create(['certification_type_id' => $type->id]);

        expect($certification->certificationType->name)->toBe('CPR Certification');
    });

    it('has files relationship', function () {
        $certification = Certification::factory()->create();

        CertificationFile::factory()->create(['certification_id' => $certification->id]);
        CertificationFile::factory()->create(['certification_id' => $certification->id]);

        expect($certification->files)->toHaveCount(2);
    });

    it('filters by employee with scope', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        Certification::factory()->create(['employee_id' => $employee1->id]);
        Certification::factory()->create(['employee_id' => $employee2->id]);

        expect(Certification::forEmployee($employee1->id)->count())->toBe(1);
    });

    it('filters active certifications with scope', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
        ]);

        expect(Certification::active()->count())->toBe(1);
    });

    it('filters pending approval certifications with scope', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::PendingApproval,
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);

        expect(Certification::pendingApproval()->count())->toBe(1);
    });

    it('filters expiring certifications within days', function () {
        $employee = Employee::factory()->create();

        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
            'expiry_date' => now()->addDays(15),
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
            'expiry_date' => now()->addDays(45),
        ]);

        expect(Certification::expiringWithin(30)->count())->toBe(1);
    });

    it('has canBeEdited attribute based on status', function () {
        $draftCert = Certification::factory()->create(['status' => CertificationStatus::Draft]);
        $activeCert = Certification::factory()->create(['status' => CertificationStatus::Active]);

        expect($draftCert->canBeEdited)->toBeTrue();
        expect($activeCert->canBeEdited)->toBeFalse();
    });

    it('has canBeSubmitted attribute based on status and files', function () {
        $draftCertWithFile = Certification::factory()->create(['status' => CertificationStatus::Draft]);
        CertificationFile::factory()->create(['certification_id' => $draftCertWithFile->id]);

        $draftCertNoFile = Certification::factory()->create(['status' => CertificationStatus::Draft]);
        $pendingCert = Certification::factory()->create(['status' => CertificationStatus::PendingApproval]);

        expect($draftCertWithFile->canBeSubmitted)->toBeTrue();
        expect($draftCertNoFile->canBeSubmitted)->toBeFalse(); // No file attached
        expect($pendingCert->canBeSubmitted)->toBeFalse();
    });

    it('calculates days until expiry correctly', function () {
        $certification = Certification::factory()->create([
            'expiry_date' => now()->addDays(30),
        ]);

        expect((int) $certification->daysUntilExpiry)->toBe(30);
    });

    it('identifies expiring soon correctly', function () {
        $expiringSoon = Certification::factory()->create([
            'status' => CertificationStatus::Active,
            'expiry_date' => now()->addDays(25),
        ]);

        $notExpiringSoon = Certification::factory()->create([
            'status' => CertificationStatus::Active,
            'expiry_date' => now()->addDays(60),
        ]);

        expect($expiringSoon->isExpiringSoon)->toBeTrue();
        expect($notExpiringSoon->isExpiringSoon)->toBeFalse();
    });
});

describe('Certification Status Transitions', function () {
    it('can submit draft certification for approval', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::Draft]);

        // Add a file so it can be submitted
        CertificationFile::factory()->create(['certification_id' => $certification->id]);

        $certification->submit();

        expect($certification->fresh()->status)->toBe(CertificationStatus::PendingApproval);
        expect($certification->fresh()->submitted_at)->not->toBeNull();
    });

    it('can approve pending certification', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::PendingApproval]);

        $certification->approve(1);

        expect($certification->fresh()->status)->toBe(CertificationStatus::Active);
        expect($certification->fresh()->approved_at)->not->toBeNull();
    });

    it('can reject pending certification', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::PendingApproval]);

        $certification->reject(1, 'Document is illegible');

        expect($certification->fresh()->status)->toBe(CertificationStatus::Draft);
        expect($certification->fresh()->rejected_at)->not->toBeNull();
    });

    it('can revoke active certification', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::Active]);

        $certification->revoke(1, 'Employee terminated');

        expect($certification->fresh()->status)->toBe(CertificationStatus::Revoked);
    });

    it('can mark active certification as expired', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::Active]);

        $certification->markAsExpired();

        expect($certification->fresh()->status)->toBe(CertificationStatus::Expired);
    });

    it('returns false when invalid transition attempted', function () {
        $certification = Certification::factory()->create(['status' => CertificationStatus::Expired]);

        $result = $certification->submit();

        expect($result)->toBeFalse();
        expect($certification->fresh()->status)->toBe(CertificationStatus::Expired);
    });
});

describe('CertificationFile Model', function () {
    it('can be created with factory', function () {
        $certification = Certification::factory()->create();
        $file = CertificationFile::factory()->create([
            'certification_id' => $certification->id,
            'original_filename' => 'certificate.pdf',
        ]);

        expect($file)->toBeInstanceOf(CertificationFile::class);
        expect($file->original_filename)->toBe('certificate.pdf');
    });

    it('has certification relationship', function () {
        $certification = Certification::factory()->create();
        $file = CertificationFile::factory()->create(['certification_id' => $certification->id]);

        expect($file->certification->id)->toBe($certification->id);
    });

    it('formats file size correctly', function () {
        $file = CertificationFile::factory()->create(['file_size' => 1024 * 500]); // 500 KB

        expect($file->formatted_file_size)->toContain('KB');
    });
});
