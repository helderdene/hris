<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Services\DocumentStorageService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessCardController extends Controller
{
    /**
     * Display the public business card page.
     */
    public function show(string $token): InertiaResponse
    {
        $employee = $this->findEmployee($token);
        $businessInfo = tenant()->business_info ?? [];

        return Inertia::render('BusinessCard/Show', [
            'employee' => [
                'full_name' => $employee->full_name,
                'initials' => $employee->initials,
                'profile_photo_url' => $employee->getProfilePhoto()
                    ? route('business-card.photo', [
                        'tenant' => tenant()->slug,
                        'token' => $employee->business_card_token,
                    ])
                    : null,
                'position' => $employee->position?->title,
                'department' => $employee->department?->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'token' => $employee->business_card_token,
                'work_location' => $employee->workLocation ? [
                    'name' => $employee->workLocation->name,
                    'address' => $employee->workLocation->address,
                    'city' => $employee->workLocation->city,
                    'region' => $employee->workLocation->region,
                    'country' => $employee->workLocation->country,
                ] : null,
            ],
            'company' => [
                'website' => $businessInfo['website'] ?? null,
                'linkedin' => $businessInfo['linkedin'] ?? null,
                'facebook' => $businessInfo['facebook'] ?? null,
                'instagram' => $businessInfo['instagram'] ?? null,
                'twitter' => $businessInfo['twitter'] ?? null,
            ],
        ]);
    }

    /**
     * Download the vCard file for the employee.
     */
    public function downloadVcard(string $token): Response
    {
        $employee = $this->findEmployee($token);

        $vcard = $this->buildVcard($employee);
        $filename = str_replace(' ', '_', $employee->full_name).'.vcf';

        return response($vcard, 200, [
            'Content-Type' => 'text/vcard',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Serve the employee's profile photo publicly for the business card.
     */
    public function photo(string $token): StreamedResponse
    {
        $employee = $this->findEmployee($token);

        $document = $employee->getProfilePhoto();
        abort_unless($document, 404);

        $version = $document->versions()->orderByDesc('version_number')->first();
        abort_unless($version, 404);

        $disk = Storage::disk(DocumentStorageService::getDiskName());
        abort_unless($disk->exists($version->file_path), 404);

        return $disk->response($version->file_path, $document->original_filename, [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_filename).'"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Find an employee by business card token or abort 404.
     */
    private function findEmployee(string $token): Employee
    {
        return Employee::query()
            ->where('business_card_token', $token)
            ->where('business_card_enabled', true)
            ->where('employment_status', EmploymentStatus::Active)
            ->with(['department', 'position', 'workLocation'])
            ->firstOrFail();
    }

    /**
     * Build a vCard 3.0 string for the employee.
     */
    private function buildVcard(Employee $employee): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.$this->escapeVcard($employee->full_name),
            'ORG:'.$this->escapeVcard(tenant()->name),
            'TITLE:'.$this->escapeVcard($employee->position?->title ?? ''),
        ];

        if ($employee->phone) {
            $lines[] = 'TEL;TYPE=WORK:'.$this->escapeVcard($employee->phone);
        }

        if ($employee->email) {
            $lines[] = 'EMAIL;TYPE=WORK:'.$this->escapeVcard($employee->email);
        }

        if ($employee->workLocation) {
            $location = $employee->workLocation;
            $lines[] = 'ADR;TYPE=WORK:;;'
                .$this->escapeVcard($location->address ?? '')
                .';'.$this->escapeVcard($location->city ?? '')
                .';'.$this->escapeVcard($location->region ?? '')
                .';;'.$this->escapeVcard($location->country ?? '');
        }

        $website = tenant()->business_info['website'] ?? null;
        if ($website) {
            $lines[] = 'URL:'.$this->escapeVcard($website);
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Escape special characters for vCard values.
     */
    private function escapeVcard(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\n"],
            ['\\\\', '\\,', '\\;', '\\n'],
            $value
        );
    }
}
