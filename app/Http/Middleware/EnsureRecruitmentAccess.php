<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecruitmentAccess
{
    /**
     * Handle an incoming request.
     *
     * Reading recruitment data needs the recruitment-view permission; changing
     * it needs recruitment-manage. Individual actions may add stricter checks.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Gate::authorize($request->isMethodSafe() ? 'can-view-recruitment' : 'can-manage-recruitment');

        return $next($request);
    }
}
