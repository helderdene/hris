<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the KasamaHR app name in the page title', function () {
    config(['app.main_domain' => 'kasamahr.test']);

    $response = $this->get('http://kasamahr.test/');

    $response->assertOk();
    $response->assertSee('<title inertia>'.config('app.name').'</title>', false);
    $response->assertDontSee('<title inertia>Laravel</title>', false);
});
