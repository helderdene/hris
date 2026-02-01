<?php

/**
 * Tests for the Password Confirmation Modal component.
 *
 * These tests verify the password confirmation modal functionality
 * including opening, submission to the confirm endpoint, and closing
 * on success. The modal is used for re-authentication of sensitive actions.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('Password Confirmation Modal', function () {
    it('allows password confirmation submission to the confirm endpoint', function () {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('password.confirm.store'), [
                'password' => 'correct-password',
            ]);

        // Fortify returns 201 Created on successful password confirmation
        $response->assertStatus(201);
    });

    it('returns error on invalid password', function () {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('password.confirm.store'), [
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    });

    it('tracks password confirmation status in session', function () {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        // Before confirmation, status should show not confirmed
        $statusResponse = $this->actingAs($user)
            ->getJson(route('password.confirmation'));

        $statusResponse->assertOk();
        $statusResponse->assertJson(['confirmed' => false]);

        // After confirmation, status should show confirmed
        $this->actingAs($user)
            ->postJson(route('password.confirm.store'), [
                'password' => 'correct-password',
            ])
            ->assertStatus(201);

        $statusResponse = $this->actingAs($user)
            ->getJson(route('password.confirmation'));

        $statusResponse->assertOk();
        $statusResponse->assertJson(['confirmed' => true]);
    });
});
