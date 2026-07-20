<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links the brand favicon set on rendered pages', function () {
    config(['app.main_domain' => 'kasamahr.test']);

    $response = $this->get('http://kasamahr.test/');

    $response->assertOk();
    $response->assertSee('/favicon.svg?v=2', false);
    $response->assertSee('/favicon.ico?v=2', false);
    $response->assertSee('/apple-touch-icon.png?v=2', false);
});

it('serves the favicon assets', function () {
    foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'] as $asset) {
        expect(file_exists(public_path($asset)))->toBeTrue();
        expect(filesize(public_path($asset)))->toBeGreaterThan(0);
    }
});
