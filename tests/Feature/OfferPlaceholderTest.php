<?php

use App\Services\Recruitment\OfferService;

it('resolves all placeholders correctly', function () {
    $service = app(OfferService::class);

    $template = 'Dear {{candidate_name}}, position: {{position}}, salary: {{salary}}, start: {{start_date}}, benefits: {{benefits}}, dept: {{department}}, location: {{work_location}}, type: {{employment_type}}, company: {{company_name}}, expiry: {{expiry_date}}';

    $data = [
        'candidate_name' => 'Jane Doe',
        'position_title' => 'Software Engineer',
        'salary' => 50000,
        'salary_currency' => 'PHP',
        'start_date' => '2025-03-01',
        'benefits' => ['Health Insurance', 'Dental'],
        'department' => 'Engineering',
        'work_location' => 'Manila',
        'employment_type' => 'full_time',
        'company_name' => 'Acme Corp',
        'expiry_date' => '2025-02-15',
    ];

    $result = $service->resolvePlaceholders($template, $data);

    expect($result)
        ->toContain('Jane Doe')
        ->toContain('Software Engineer')
        ->toContain('50,000.00 PHP')
        ->toContain('2025-03-01')
        ->toContain('Health Insurance, Dental')
        ->toContain('Engineering')
        ->toContain('Manila')
        ->toContain('full_time')
        ->toContain('Acme Corp')
        ->toContain('2025-02-15');
});

it('handles missing placeholder data gracefully', function () {
    $service = app(OfferService::class);

    $template = 'Dear {{candidate_name}}, position: {{position}}, salary: {{salary}}, dept: {{department}}';

    $result = $service->resolvePlaceholders($template, []);

    expect($result)
        ->toContain('Dear ,')
        ->toContain('position: ,')
        ->toContain('salary: 0.00')
        ->toContain('dept: ');
});
