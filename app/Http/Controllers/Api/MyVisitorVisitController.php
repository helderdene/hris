<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VisitorVisitResource;
use App\Models\Employee;
use App\Models\VisitorVisit;
use App\Services\Visitor\VisitorRegistrationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MyVisitorVisitController extends Controller
{
    /**
     * Host employee approves a visitor visit.
     */
    public function approve(Request $request, VisitorVisit $visit, VisitorRegistrationService $service): VisitorVisitResource
    {
        $this->authorizeHost($request, $visit);

        $visit = $service->hostApprove($visit, $request->user());
        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Host employee rejects a visitor visit.
     */
    public function reject(Request $request, VisitorVisit $visit, VisitorRegistrationService $service): VisitorVisitResource
    {
        $this->authorizeHost($request, $visit);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $visit = $service->hostReject($visit, $request->user(), $request->input('reason'));
        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Ensure the authenticated user is the host employee for this visit.
     */
    private function authorizeHost(Request $request, VisitorVisit $visit): void
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee || $visit->host_employee_id !== $employee->id) {
            throw new AccessDeniedHttpException('You are not the host for this visit.');
        }
    }
}
