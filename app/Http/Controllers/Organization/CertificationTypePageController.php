<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificationTypeResource;
use App\Models\CertificationType;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for rendering the Certification Types management page.
 */
class CertificationTypePageController extends Controller
{
    /**
     * Display the certification types management page.
     */
    public function __invoke(): Response
    {
        Gate::authorize('can-manage-organization');

        $certificationTypes = CertificationType::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/CertificationTypes/Index', [
            'certificationTypes' => CertificationTypeResource::collection($certificationTypes),
        ]);
    }
}
