<?php

use App\Enums\LoanStatus;

it('has all expected loan statuses', function () {
    expect(LoanStatus::cases())->toHaveCount(4);

    expect(LoanStatus::Active->value)->toBe('active');
    expect(LoanStatus::Completed->value)->toBe('completed');
    expect(LoanStatus::OnHold->value)->toBe('on_hold');
    expect(LoanStatus::Cancelled->value)->toBe('cancelled');
});

it('returns correct labels', function () {
    expect(LoanStatus::Active->label())->toBe('Active');
    expect(LoanStatus::Completed->label())->toBe('Completed');
    expect(LoanStatus::OnHold->label())->toBe('On Hold');
    expect(LoanStatus::Cancelled->label())->toBe('Cancelled');
});

it('correctly identifies deductible statuses', function () {
    expect(LoanStatus::Active->isDeductible())->toBeTrue();

    expect(LoanStatus::Completed->isDeductible())->toBeFalse();
    expect(LoanStatus::OnHold->isDeductible())->toBeFalse();
    expect(LoanStatus::Cancelled->isDeductible())->toBeFalse();
});

it('returns correct allowed transitions for active status', function () {
    $transitions = LoanStatus::Active->allowedTransitions();

    expect($transitions)->toContain(LoanStatus::OnHold);
    expect($transitions)->toContain(LoanStatus::Completed);
    expect($transitions)->toContain(LoanStatus::Cancelled);
    expect($transitions)->not->toContain(LoanStatus::Active);
});

it('returns correct allowed transitions for on_hold status', function () {
    $transitions = LoanStatus::OnHold->allowedTransitions();

    expect($transitions)->toContain(LoanStatus::Active);
    expect($transitions)->toContain(LoanStatus::Cancelled);
    expect($transitions)->not->toContain(LoanStatus::Completed);
});

it('returns no transitions for completed status', function () {
    $transitions = LoanStatus::Completed->allowedTransitions();

    expect($transitions)->toBeEmpty();
});

it('returns no transitions for cancelled status', function () {
    $transitions = LoanStatus::Cancelled->allowedTransitions();

    expect($transitions)->toBeEmpty();
});

it('correctly validates status transitions', function () {
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::OnHold))->toBeTrue();
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::Completed))->toBeTrue();
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::Cancelled))->toBeTrue();
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::Active))->toBeFalse();

    expect(LoanStatus::OnHold->canTransitionTo(LoanStatus::Active))->toBeTrue();
    expect(LoanStatus::OnHold->canTransitionTo(LoanStatus::Completed))->toBeFalse();

    expect(LoanStatus::Completed->canTransitionTo(LoanStatus::Active))->toBeFalse();
    expect(LoanStatus::Cancelled->canTransitionTo(LoanStatus::Active))->toBeFalse();
});

it('returns correct badge colors', function () {
    expect(LoanStatus::Active->color())->toBe('green');
    expect(LoanStatus::Completed->color())->toBe('blue');
    expect(LoanStatus::OnHold->color())->toBe('amber');
    expect(LoanStatus::Cancelled->color())->toBe('slate');
});

it('returns options formatted for frontend', function () {
    $options = LoanStatus::options();

    expect($options)->toHaveCount(4);
    expect($options[0])->toMatchArray([
        'value' => 'active',
        'label' => 'Active',
        'color' => 'green',
    ]);
});
