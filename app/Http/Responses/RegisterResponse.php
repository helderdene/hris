<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * After registration, redirect user to create their organization.
     * New users always need to create a tenant before they can use the system.
     */
    public function toResponse($request): Response
    {
        return redirect()->route('tenant.register');
    }
}
