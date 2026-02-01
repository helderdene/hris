<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * The main domains that should bypass tenant resolution.
     *
     * @var array<int, string>
     */
    protected array $mainDomains = [
        'kasamahr.com',
        'kasamahr.test',
        'localhost',
    ];

    /**
     * Handle an incoming request.
     *
     * Extracts the subdomain from the request host and resolves it to a tenant.
     * Main domain requests bypass tenant resolution.
     * Returns 404 for unrecognized subdomains.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Check if this is the main domain (bypass tenant resolution)
        if ($this->isMainDomain($host)) {
            return $next($request);
        }

        // Extract subdomain from host
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain === null) {
            return $next($request);
        }

        // Look up tenant by slug
        $tenant = Tenant::where('slug', $subdomain)->first();

        if ($tenant === null) {
            abort(404, 'Tenant not found');
        }

        // Bind tenant to app container as singleton
        app()->instance('tenant', $tenant);

        return $next($request);
    }

    /**
     * Check if the host is a main domain that should bypass tenant resolution.
     */
    protected function isMainDomain(string $host): bool
    {
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);

        return in_array($host, $this->mainDomains, true);
    }

    /**
     * Extract the subdomain from the host.
     *
     * For example, "acme.kasamahr.test" returns "acme"
     * For "kasamahr.test" returns null
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);

        // Check for each main domain pattern
        foreach ($this->mainDomains as $mainDomain) {
            $pattern = '/^([a-z0-9-]+)\.'.preg_quote($mainDomain, '/').'$/i';

            if (preg_match($pattern, $host, $matches)) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }
}
