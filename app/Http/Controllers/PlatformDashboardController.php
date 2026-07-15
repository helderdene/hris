<?php

namespace App\Http\Controllers;

use App\Http\Responses\LoginResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformDashboardController extends Controller
{
    public function __construct(protected LoginResponse $loginResponse) {}

    /**
     * Route authenticated users landing on the platform dashboard.
     *
     * The main domain has no dashboard of its own; reuse the post-login
     * tenant-membership routing so users end up on their tenant subdomain,
     * the tenant selector, the admin dashboard, or tenant registration.
     */
    public function __invoke(Request $request): Response
    {
        return $this->loginResponse->toResponse($request);
    }
}
