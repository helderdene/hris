<?php

use App\Enums\LoanApplicationStatus;

it('has correct labels', function () {
    expect(LoanApplicationStatus::Draft->label())->toBe('Draft');
    expect(LoanApplicationStatus::Pending->label())->toBe('Pending Approval');
    expect(LoanApplicationStatus::Approved->label())->toBe('Approved');
    expect(LoanApplicationStatus::Rejected->label())->toBe('Rejected');
    expect(LoanApplicationStatus::Cancelled->label())->toBe('Cancelled');
});

it('has correct colors', function () {
    expect(LoanApplicationStatus::Draft->color())->toBe('slate');
    expect(LoanApplicationStatus::Pending->color())->toBe('amber');
    expect(LoanApplicationStatus::Approved->color())->toBe('green');
    expect(LoanApplicationStatus::Rejected->color())->toBe('red');
    expect(LoanApplicationStatus::Cancelled->color())->toBe('slate');
});

it('allows correct transitions from draft', function () {
    $transitions = LoanApplicationStatus::Draft->allowedTransitions();

    expect($transitions)->toContain(LoanApplicationStatus::Pending);
    expect($transitions)->toContain(LoanApplicationStatus::Cancelled);
    expect($transitions)->not->toContain(LoanApplicationStatus::Approved);
    expect($transitions)->not->toContain(LoanApplicationStatus::Rejected);
});

it('allows correct transitions from pending', function () {
    $transitions = LoanApplicationStatus::Pending->allowedTransitions();

    expect($transitions)->toContain(LoanApplicationStatus::Approved);
    expect($transitions)->toContain(LoanApplicationStatus::Rejected);
    expect($transitions)->toContain(LoanApplicationStatus::Cancelled);
});

it('has no transitions from final statuses', function () {
    expect(LoanApplicationStatus::Approved->allowedTransitions())->toBeEmpty();
    expect(LoanApplicationStatus::Rejected->allowedTransitions())->toBeEmpty();
    expect(LoanApplicationStatus::Cancelled->allowedTransitions())->toBeEmpty();
});

it('correctly checks can transition to', function () {
    expect(LoanApplicationStatus::Draft->canTransitionTo(LoanApplicationStatus::Pending))->toBeTrue();
    expect(LoanApplicationStatus::Draft->canTransitionTo(LoanApplicationStatus::Approved))->toBeFalse();
    expect(LoanApplicationStatus::Pending->canTransitionTo(LoanApplicationStatus::Approved))->toBeTrue();
});

it('correctly identifies final statuses', function () {
    expect(LoanApplicationStatus::Draft->isFinal())->toBeFalse();
    expect(LoanApplicationStatus::Pending->isFinal())->toBeFalse();
    expect(LoanApplicationStatus::Approved->isFinal())->toBeTrue();
    expect(LoanApplicationStatus::Rejected->isFinal())->toBeTrue();
    expect(LoanApplicationStatus::Cancelled->isFinal())->toBeTrue();
});

it('correctly identifies editable status', function () {
    expect(LoanApplicationStatus::Draft->canBeEdited())->toBeTrue();
    expect(LoanApplicationStatus::Pending->canBeEdited())->toBeFalse();
    expect(LoanApplicationStatus::Approved->canBeEdited())->toBeFalse();
});

it('correctly identifies cancellable statuses', function () {
    expect(LoanApplicationStatus::Draft->canBeCancelled())->toBeTrue();
    expect(LoanApplicationStatus::Pending->canBeCancelled())->toBeTrue();
    expect(LoanApplicationStatus::Approved->canBeCancelled())->toBeFalse();
    expect(LoanApplicationStatus::Rejected->canBeCancelled())->toBeFalse();
});

it('returns all values', function () {
    $values = LoanApplicationStatus::values();

    expect($values)->toContain('draft', 'pending', 'approved', 'rejected', 'cancelled');
    expect($values)->toHaveCount(5);
});

it('returns options for frontend', function () {
    $options = LoanApplicationStatus::options();

    expect($options)->toHaveCount(5);
    expect($options[0])->toHaveKeys(['value', 'label', 'color']);
});
