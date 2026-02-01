<?php

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Enums\LocationType;

describe('JobLevel Enum', function () {
    it('has all 7 job level cases', function () {
        $cases = JobLevel::cases();

        expect($cases)->toHaveCount(7);
        expect(JobLevel::Junior->value)->toBe('junior');
        expect(JobLevel::Mid->value)->toBe('mid');
        expect(JobLevel::Senior->value)->toBe('senior');
        expect(JobLevel::Lead->value)->toBe('lead');
        expect(JobLevel::Manager->value)->toBe('manager');
        expect(JobLevel::Director->value)->toBe('director');
        expect(JobLevel::Executive->value)->toBe('executive');
    });

    it('provides human-readable labels for each job level', function () {
        expect(JobLevel::Junior->label())->toBe('Junior');
        expect(JobLevel::Mid->label())->toBe('Mid');
        expect(JobLevel::Senior->label())->toBe('Senior');
        expect(JobLevel::Lead->label())->toBe('Lead');
        expect(JobLevel::Manager->label())->toBe('Manager');
        expect(JobLevel::Director->label())->toBe('Director');
        expect(JobLevel::Executive->label())->toBe('Executive');
    });

    it('returns all job level values as array', function () {
        $values = JobLevel::values();

        expect($values)->toBeArray();
        expect($values)->toContain('junior');
        expect($values)->toContain('mid');
        expect($values)->toContain('senior');
        expect($values)->toContain('lead');
        expect($values)->toContain('manager');
        expect($values)->toContain('director');
        expect($values)->toContain('executive');
    });

    it('validates job levels correctly with isValid method', function () {
        expect(JobLevel::isValid('junior'))->toBeTrue();
        expect(JobLevel::isValid('mid'))->toBeTrue();
        expect(JobLevel::isValid('senior'))->toBeTrue();
        expect(JobLevel::isValid('lead'))->toBeTrue();
        expect(JobLevel::isValid('manager'))->toBeTrue();
        expect(JobLevel::isValid('director'))->toBeTrue();
        expect(JobLevel::isValid('executive'))->toBeTrue();
        expect(JobLevel::isValid('invalid_level'))->toBeFalse();
        expect(JobLevel::isValid('intern'))->toBeFalse();
    });

    it('creates job level from value with tryFromValue', function () {
        expect(JobLevel::tryFromValue('junior'))->toBe(JobLevel::Junior);
        expect(JobLevel::tryFromValue('executive'))->toBe(JobLevel::Executive);
        expect(JobLevel::tryFromValue('invalid'))->toBeNull();
    });
});

describe('EmploymentType Enum', function () {
    it('has all 6 employment type cases', function () {
        $cases = EmploymentType::cases();

        expect($cases)->toHaveCount(6);
        expect(EmploymentType::Regular->value)->toBe('regular');
        expect(EmploymentType::Probationary->value)->toBe('probationary');
        expect(EmploymentType::Contractual->value)->toBe('contractual');
        expect(EmploymentType::Consultant->value)->toBe('consultant');
        expect(EmploymentType::Intern->value)->toBe('intern');
        expect(EmploymentType::ProjectBased->value)->toBe('project_based');
    });

    it('provides human-readable labels for each employment type', function () {
        expect(EmploymentType::Regular->label())->toBe('Regular');
        expect(EmploymentType::Probationary->label())->toBe('Probationary');
        expect(EmploymentType::Contractual->label())->toBe('Contractual');
        expect(EmploymentType::Consultant->label())->toBe('Consultant');
        expect(EmploymentType::Intern->label())->toBe('Intern');
        expect(EmploymentType::ProjectBased->label())->toBe('Project-based');
    });

    it('returns all employment type values as array', function () {
        $values = EmploymentType::values();

        expect($values)->toBeArray();
        expect($values)->toContain('regular');
        expect($values)->toContain('probationary');
        expect($values)->toContain('contractual');
        expect($values)->toContain('consultant');
        expect($values)->toContain('intern');
        expect($values)->toContain('project_based');
    });

    it('validates employment types correctly with isValid method', function () {
        expect(EmploymentType::isValid('regular'))->toBeTrue();
        expect(EmploymentType::isValid('probationary'))->toBeTrue();
        expect(EmploymentType::isValid('contractual'))->toBeTrue();
        expect(EmploymentType::isValid('consultant'))->toBeTrue();
        expect(EmploymentType::isValid('intern'))->toBeTrue();
        expect(EmploymentType::isValid('project_based'))->toBeTrue();
        expect(EmploymentType::isValid('invalid_type'))->toBeFalse();
        expect(EmploymentType::isValid('full_time'))->toBeFalse();
    });

    it('creates employment type from value with tryFromValue', function () {
        expect(EmploymentType::tryFromValue('regular'))->toBe(EmploymentType::Regular);
        expect(EmploymentType::tryFromValue('intern'))->toBe(EmploymentType::Intern);
        expect(EmploymentType::tryFromValue('project_based'))->toBe(EmploymentType::ProjectBased);
        expect(EmploymentType::tryFromValue('invalid'))->toBeNull();
    });
});

describe('LocationType Enum', function () {
    it('has all 6 location type cases', function () {
        $cases = LocationType::cases();

        expect($cases)->toHaveCount(6);
        expect(LocationType::Headquarters->value)->toBe('headquarters');
        expect(LocationType::Branch->value)->toBe('branch');
        expect(LocationType::SatelliteOffice->value)->toBe('satellite_office');
        expect(LocationType::RemoteHub->value)->toBe('remote_hub');
        expect(LocationType::Warehouse->value)->toBe('warehouse');
        expect(LocationType::Factory->value)->toBe('factory');
    });

    it('provides human-readable labels for each location type', function () {
        expect(LocationType::Headquarters->label())->toBe('Headquarters');
        expect(LocationType::Branch->label())->toBe('Branch');
        expect(LocationType::SatelliteOffice->label())->toBe('Satellite Office');
        expect(LocationType::RemoteHub->label())->toBe('Remote Hub');
        expect(LocationType::Warehouse->label())->toBe('Warehouse');
        expect(LocationType::Factory->label())->toBe('Factory');
    });

    it('returns all location type values as array', function () {
        $values = LocationType::values();

        expect($values)->toBeArray();
        expect($values)->toContain('headquarters');
        expect($values)->toContain('branch');
        expect($values)->toContain('satellite_office');
        expect($values)->toContain('remote_hub');
        expect($values)->toContain('warehouse');
        expect($values)->toContain('factory');
    });

    it('validates location types correctly with isValid method', function () {
        expect(LocationType::isValid('headquarters'))->toBeTrue();
        expect(LocationType::isValid('branch'))->toBeTrue();
        expect(LocationType::isValid('satellite_office'))->toBeTrue();
        expect(LocationType::isValid('remote_hub'))->toBeTrue();
        expect(LocationType::isValid('warehouse'))->toBeTrue();
        expect(LocationType::isValid('factory'))->toBeTrue();
        expect(LocationType::isValid('invalid_type'))->toBeFalse();
        expect(LocationType::isValid('office'))->toBeFalse();
    });

    it('creates location type from value with tryFromValue', function () {
        expect(LocationType::tryFromValue('headquarters'))->toBe(LocationType::Headquarters);
        expect(LocationType::tryFromValue('factory'))->toBe(LocationType::Factory);
        expect(LocationType::tryFromValue('invalid'))->toBeNull();
    });
});
