<?php

namespace App\Http\Controllers\Hr;

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentRequestType;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DocumentRequestManagementController extends Controller
{
    /**
     * Display the HR document requests management page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = DocumentRequest::query()
            ->with(['employee.department', 'employee.position'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->input('document_type'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $documentRequests = $query->paginate(25);

        $employees = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix']);

        $summary = [
            'total_requests' => DocumentRequest::count(),
            'pending' => DocumentRequest::where('status', DocumentRequestStatus::Pending)->count(),
            'processing' => DocumentRequest::where('status', DocumentRequestStatus::Processing)->count(),
            'ready' => DocumentRequest::where('status', DocumentRequestStatus::Ready)->count(),
        ];

        return Inertia::render('Hr/DocumentRequests/Index', [
            'documentRequests' => DocumentRequestResource::collection($documentRequests),
            'employees' => $employees->map(fn ($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'full_name' => $emp->full_name,
            ]),
            'filters' => [
                'status' => $request->input('status'),
                'document_type' => $request->input('document_type'),
                'employee_id' => $request->input('employee_id') ? (int) $request->input('employee_id') : null,
            ],
            'summary' => $summary,
            'statuses' => DocumentRequestStatus::options(),
            'documentTypes' => DocumentRequestType::options(),
        ]);
    }
}
