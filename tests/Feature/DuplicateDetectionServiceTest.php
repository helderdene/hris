<?php

use App\Models\Candidate;
use App\Models\Tenant;
use App\Services\Recruitment\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $tenant = Tenant::factory()->create();
    app()->instance('tenant', $tenant);
});

describe('DuplicateDetectionService', function () {
    it('finds exact matches by email', function () {
        $candidate = Candidate::factory()->create(['email' => 'john@example.com']);

        $service = new DuplicateDetectionService;
        $results = $service->findDuplicates('john@example.com', null, null, null);

        expect($results['exact'])->toHaveCount(1);
        expect($results['exact']->first()->id)->toBe($candidate->id);
    });

    it('finds exact matches by phone', function () {
        $candidate = Candidate::factory()->create(['phone' => '+639171234567']);

        $service = new DuplicateDetectionService;
        $results = $service->findDuplicates(null, '+639171234567', null, null);

        expect($results['exact'])->toHaveCount(1);
        expect($results['exact']->first()->id)->toBe($candidate->id);
    });

    it('finds potential matches by similar name', function () {
        $candidate = Candidate::factory()->create([
            'first_name' => 'Jonathan',
            'last_name' => 'Smith',
        ]);

        $service = new DuplicateDetectionService;
        $results = $service->findDuplicates(null, null, 'Jon', 'Smi');

        expect($results['potential'])->toHaveCount(1);
    });

    it('excludes specified candidate id', function () {
        $candidate = Candidate::factory()->create(['email' => 'john@example.com']);

        $service = new DuplicateDetectionService;
        $results = $service->findDuplicates('john@example.com', null, null, null, $candidate->id);

        expect($results['exact'])->toBeEmpty();
    });

    it('returns empty when no matches', function () {
        Candidate::factory()->create(['email' => 'other@example.com']);

        $service = new DuplicateDetectionService;
        $results = $service->findDuplicates('nonexistent@example.com', null, null, null);

        expect($results['exact'])->toBeEmpty();
        expect($results['potential'])->toBeEmpty();
    });
});
