<?php

use App\Enums\ApplicationStatus;

describe('ApplicationStatus', function () {
    it('has correct labels for all statuses', function () {
        expect(ApplicationStatus::Applied->label())->toBe('Applied');
        expect(ApplicationStatus::Screening->label())->toBe('Screening');
        expect(ApplicationStatus::Hired->label())->toBe('Hired');
        expect(ApplicationStatus::Rejected->label())->toBe('Rejected');
        expect(ApplicationStatus::Withdrawn->label())->toBe('Withdrawn');
    });

    it('identifies terminal statuses correctly', function () {
        expect(ApplicationStatus::Hired->isTerminal())->toBeTrue();
        expect(ApplicationStatus::Rejected->isTerminal())->toBeTrue();
        expect(ApplicationStatus::Withdrawn->isTerminal())->toBeTrue();
        expect(ApplicationStatus::Applied->isTerminal())->toBeFalse();
        expect(ApplicationStatus::Screening->isTerminal())->toBeFalse();
        expect(ApplicationStatus::Interview->isTerminal())->toBeFalse();
    });

    it('allows valid transitions from Applied', function () {
        $transitions = ApplicationStatus::Applied->allowedTransitions();
        expect($transitions)->toContain(ApplicationStatus::Screening);
        expect($transitions)->toContain(ApplicationStatus::Rejected);
        expect($transitions)->toContain(ApplicationStatus::Withdrawn);
        expect($transitions)->not->toContain(ApplicationStatus::Hired);
    });

    it('allows valid transitions from Interview', function () {
        $transitions = ApplicationStatus::Interview->allowedTransitions();
        expect($transitions)->toContain(ApplicationStatus::Assessment);
        expect($transitions)->toContain(ApplicationStatus::Offer);
        expect($transitions)->toContain(ApplicationStatus::Rejected);
        expect($transitions)->not->toContain(ApplicationStatus::Applied);
    });

    it('has no transitions from terminal statuses', function () {
        expect(ApplicationStatus::Hired->allowedTransitions())->toBeEmpty();
        expect(ApplicationStatus::Rejected->allowedTransitions())->toBeEmpty();
        expect(ApplicationStatus::Withdrawn->allowedTransitions())->toBeEmpty();
    });

    it('returns all values', function () {
        $values = ApplicationStatus::values();
        expect($values)->toContain('applied');
        expect($values)->toContain('hired');
        expect($values)->toContain('rejected');
        expect($values)->toHaveCount(8);
    });

    it('returns options for frontend', function () {
        $options = ApplicationStatus::options();
        expect($options)->toHaveCount(8);
        expect($options[0])->toHaveKeys(['value', 'label', 'color']);
    });
});
