<?php

use App\Enums\EmploymentStatus;

describe('EmploymentStatus Enum', function () {
    it('has all 6 employment status cases for lifecycle tracking', function () {
        $cases = EmploymentStatus::cases();

        expect($cases)->toHaveCount(6);
        expect(EmploymentStatus::Active->value)->toBe('active');
        expect(EmploymentStatus::Resigned->value)->toBe('resigned');
        expect(EmploymentStatus::Terminated->value)->toBe('terminated');
        expect(EmploymentStatus::Retired->value)->toBe('retired');
        expect(EmploymentStatus::EndOfContract->value)->toBe('end_of_contract');
        expect(EmploymentStatus::Deceased->value)->toBe('deceased');
    });

    it('provides human-readable labels for each employment status', function () {
        expect(EmploymentStatus::Active->label())->toBe('Active');
        expect(EmploymentStatus::Resigned->label())->toBe('Resigned');
        expect(EmploymentStatus::Terminated->label())->toBe('Terminated');
        expect(EmploymentStatus::Retired->label())->toBe('Retired');
        expect(EmploymentStatus::EndOfContract->label())->toBe('End of Contract');
        expect(EmploymentStatus::Deceased->label())->toBe('Deceased');
    });

    it('returns all employment status values as array', function () {
        $values = EmploymentStatus::values();

        expect($values)->toBeArray();
        expect($values)->toContain('active');
        expect($values)->toContain('resigned');
        expect($values)->toContain('terminated');
        expect($values)->toContain('retired');
        expect($values)->toContain('end_of_contract');
        expect($values)->toContain('deceased');
    });

    it('validates employment status correctly with isValid method', function () {
        expect(EmploymentStatus::isValid('active'))->toBeTrue();
        expect(EmploymentStatus::isValid('resigned'))->toBeTrue();
        expect(EmploymentStatus::isValid('terminated'))->toBeTrue();
        expect(EmploymentStatus::isValid('retired'))->toBeTrue();
        expect(EmploymentStatus::isValid('end_of_contract'))->toBeTrue();
        expect(EmploymentStatus::isValid('deceased'))->toBeTrue();
        expect(EmploymentStatus::isValid('invalid_status'))->toBeFalse();
        expect(EmploymentStatus::isValid('suspended'))->toBeFalse();
    });
});
