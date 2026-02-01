<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Http\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API controller for employee document request management.
 *
 * Allows authenticated employees to create and view their
 * document requests through self-service.
 */
class DocumentRequestController extends Controller
{
    /**
     * List the current user's document requests.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee($request);

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile linked to your account.',
            ], 404);
        }

        $requests = DocumentRequest::query()
            ->forEmployee($employee->id)
            ->latest()
            ->paginate(15);

        return DocumentRequestResource::collection($requests);
    }

    /**
     * Create a new document request.
     */
    public function store(StoreDocumentRequestRequest $request): DocumentRequestResource|JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee($request);

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile linked to your account.',
            ], 404);
        }

        $documentRequest = DocumentRequest::create([
            'employee_id' => $employee->id,
            'document_type' => $request->validated('document_type'),
            'status' => DocumentRequestStatus::Pending,
            'notes' => $request->validated('notes'),
        ]);

        return new DocumentRequestResource($documentRequest);
    }

    /**
     * Show a single document request.
     */
    public function show(Request $request, DocumentRequest $documentRequest): DocumentRequestResource|JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee($request);

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile linked to your account.',
            ], 404);
        }

        if ($documentRequest->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'You are not authorized to view this request.',
            ], 403);
        }

        return new DocumentRequestResource($documentRequest);
    }

    /**
     * Get the employee associated with the authenticated user.
     */
    protected function getAuthenticatedEmployee(Request $request): ?Employee
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return Employee::query()
            ->where('user_id', $user->id)
            ->first();
    }
}
