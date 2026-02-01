<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrainingWaitlistResource;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use App\Services\Training\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrainingWaitlistController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    /**
     * Display a listing of waitlist entries for a session.
     */
    public function index(string $tenant, TrainingSession $session): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-training');

        $waitlist = $session->waitlist()
            ->with(['employee.department', 'employee.position'])
            ->ordered()
            ->get();

        return TrainingWaitlistResource::collection($waitlist);
    }

    /**
     * Display the specified waitlist entry.
     */
    public function show(string $tenant, TrainingWaitlist $waitlist): TrainingWaitlistResource
    {
        Gate::authorize('can-view-training');

        // Allow employees to view their own waitlist entries
        $employee = auth()->user()->employee;
        if (! Gate::allows('can-manage-training') && $waitlist->employee_id !== $employee?->id) {
            abort(403);
        }

        $waitlist->load(['employee.department', 'employee.position', 'session.course']);

        return new TrainingWaitlistResource($waitlist);
    }

    /**
     * Remove a waitlist entry.
     */
    public function destroy(string $tenant, TrainingWaitlist $waitlist): JsonResponse
    {
        // Allow employees to remove themselves from waitlist
        $employee = auth()->user()->employee;
        $isOwn = $waitlist->employee_id === $employee?->id;

        if (! $isOwn && ! Gate::allows('can-manage-training')) {
            abort(403);
        }

        $this->enrollmentService->cancelWaitlist($waitlist);

        return response()->json([
            'message' => 'Removed from waitlist successfully.',
        ]);
    }
}
