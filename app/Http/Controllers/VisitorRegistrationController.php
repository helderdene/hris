<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitorRegistrationRequest;
use App\Models\Employee;
use App\Models\WorkLocation;
use App\Services\Visitor\VisitorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class VisitorRegistrationController extends Controller
{
    /**
     * Display the public visitor registration page.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('Visitor/Register', [
            'locations' => WorkLocation::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'companyName' => tenant()?->name,
            'companyLogo' => tenant()?->logo_path,
        ]);
    }

    /**
     * Handle public visitor registration submission.
     */
    public function store(VisitorRegistrationRequest $request, VisitorRegistrationService $registrationService): \Illuminate\Http\RedirectResponse
    {
        // Rate limit: 10 registrations per minute per IP
        $key = 'visitor-registration:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors([
                'email' => 'Too many registration attempts. Please try again in a moment.',
            ]);
        }

        RateLimiter::hit($key, 60);

        $validated = $request->validated();

        $registrationService->registerFromPublicPage(
            [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
            ],
            [
                'work_location_id' => $validated['work_location_id'],
                'host_employee_id' => $validated['host_employee_id'],
                'purpose' => $validated['purpose'],
                'expected_at' => $validated['expected_at'],
            ]
        );

        return back()->with('success', 'Your visit registration has been submitted. You will receive an email once it is approved.');
    }

    /**
     * Search employees by name for the public registration form.
     * Returns limited info (id, name) to avoid exposing sensitive data.
     */
    public function searchEmployees(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $employees = Employee::query()
            ->where(function ($q) use ($query) {
                // Split query into words to support full name searches
                $words = preg_split('/\s+/', trim($query));

                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word) {
                        $sub->where('first_name', 'like', "%{$word}%")
                            ->orWhere('last_name', 'like', "%{$word}%");
                    });
                }
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name']);

        return response()->json(
            $employees->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => "{$e->first_name} {$e->last_name}",
            ])
        );
    }
}
