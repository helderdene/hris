<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web controller for rendering the Company Documents Inertia page.
 *
 * This controller handles the web page rendering for company-wide documents.
 * The actual CRUD operations are handled by the API controller at
 * App\Http\Controllers\Api\CompanyDocumentController.
 *
 * Follows the pattern from OrganizationController for page rendering.
 */
class CompanyDocumentController extends Controller
{
    /**
     * Display the company documents index page.
     *
     * Renders the Inertia page with permission flags for the frontend.
     * The actual document data is fetched via API calls from the frontend.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function index(): Response
    {
        // All authenticated tenant users can view company documents
        Gate::authorize('can-view-company-documents');

        // Check if user can manage company documents (HR only)
        $canManageCompanyDocuments = Gate::allows('can-manage-company-documents');

        return Inertia::render('CompanyDocuments/Index', [
            'can_manage_company_documents' => $canManageCompanyDocuments,
        ]);
    }
}
