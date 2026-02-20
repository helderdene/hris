<?php

use App\Enums\AddonType;
use App\Enums\Module;
use App\Enums\SubscriptionStatus;

describe('Module enum', function () {
    it('has 22 cases', function () {
        expect(Module::cases())->toHaveCount(22);
    });

    it('has non-empty labels for all cases', function () {
        foreach (Module::cases() as $module) {
            expect($module->label())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns 10 starter modules', function () {
        expect(Module::starterModules())->toHaveCount(10);
    });

    it('returns 18 professional modules', function () {
        expect(Module::professionalModules())->toHaveCount(18);
    });

    it('returns 22 enterprise modules', function () {
        expect(Module::enterpriseModules())->toHaveCount(22);
    });

    it('has cumulative tier modules where starter is subset of professional', function () {
        $starter = Module::starterModules();
        $professional = Module::professionalModules();

        foreach ($starter as $module) {
            expect($professional)->toContain($module);
        }
    });

    it('has cumulative tier modules where professional is subset of enterprise', function () {
        $professional = Module::professionalModules();
        $enterprise = Module::enterpriseModules();

        foreach ($professional as $module) {
            expect($enterprise)->toContain($module);
        }
    });

    it('returns all values as strings', function () {
        $values = Module::values();
        expect($values)->toHaveCount(22);
        foreach ($values as $value) {
            expect($value)->toBeString();
        }
    });

    it('validates known values', function () {
        expect(Module::isValid('hr_management'))->toBeTrue();
        expect(Module::isValid('nonexistent'))->toBeFalse();
    });

    it('tries from value', function () {
        expect(Module::tryFromValue('payroll'))->toBe(Module::Payroll);
        expect(Module::tryFromValue('nonexistent'))->toBeNull();
    });
});

describe('SubscriptionStatus enum', function () {
    it('has 6 cases', function () {
        expect(SubscriptionStatus::cases())->toHaveCount(6);
    });

    it('has non-empty labels for all cases', function () {
        foreach (SubscriptionStatus::cases() as $status) {
            expect($status->label())->toBeString()->not->toBeEmpty();
        }
    });

    it('identifies active status', function () {
        expect(SubscriptionStatus::Active->isActive())->toBeTrue();
        expect(SubscriptionStatus::PastDue->isActive())->toBeFalse();
        expect(SubscriptionStatus::Cancelled->isActive())->toBeFalse();
    });

    it('validates known values', function () {
        expect(SubscriptionStatus::isValid('active'))->toBeTrue();
        expect(SubscriptionStatus::isValid('nonexistent'))->toBeFalse();
    });
});

describe('AddonType enum', function () {
    it('has 2 cases', function () {
        expect(AddonType::cases())->toHaveCount(2);
    });

    it('has non-empty labels for all cases', function () {
        foreach (AddonType::cases() as $type) {
            expect($type->label())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns correct units per quantity for employee slots', function () {
        expect(AddonType::EmployeeSlots->unitsPerQuantity())->toBe(10);
    });

    it('returns correct units per quantity for biometric devices', function () {
        expect(AddonType::BiometricDevices->unitsPerQuantity())->toBe(1);
    });

    it('returns correct default price for employee slots', function () {
        expect(AddonType::EmployeeSlots->defaultPrice())->toBe(2500);
    });

    it('returns correct default price for biometric devices', function () {
        expect(AddonType::BiometricDevices->defaultPrice())->toBe(5000);
    });
});
