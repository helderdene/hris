<?php

use Laravel\Fortify\Features;

describe('Fortify Configuration', function () {
    it('has 2FA features disabled', function () {
        // Two-factor authentication should be disabled per spec requirements
        // This is marked as a future enhancement
        $features = config('fortify.features');

        // Check that 2FA is NOT in the features array
        $has2FA = collect($features)->contains(function ($feature) {
            // Features can be either strings or arrays with feature name as first element
            if (is_array($feature)) {
                return str_contains($feature[0] ?? '', 'two-factor');
            }

            return str_contains((string) $feature, 'two-factor');
        });

        expect($has2FA)->toBeFalse('Two-factor authentication should be disabled');
    });

    it('has password confirmation timeout set to 3 hours', function () {
        // Password confirmation should be valid for 3 hours (10800 seconds)
        // This is used for re-authentication of sensitive actions
        $timeout = config('auth.password_timeout');

        expect($timeout)->toBe(10800);
    });

    it('has registration feature enabled for tenant creation', function () {
        // Registration should remain enabled for initial tenant creation by HR Admin
        expect(Features::enabled(Features::registration()))->toBeTrue();
    });

    it('has required authentication features enabled', function () {
        // These features should remain enabled per spec requirements:
        // - registration (for tenant creation)
        // - resetPasswords (for password reset flow)
        // - emailVerification (for verifying new users)
        expect(Features::enabled(Features::registration()))->toBeTrue();
        expect(Features::enabled(Features::resetPasswords()))->toBeTrue();
        expect(Features::enabled(Features::emailVerification()))->toBeTrue();
    });
});
