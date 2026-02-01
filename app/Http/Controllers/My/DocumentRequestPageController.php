<?php

namespace App\Http\Controllers\My;

use App\Enums\DocumentRequestType;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page controller for employee self-service document requests.
 */
class DocumentRequestPageController extends Controller
{
    /**
     * Display the document requests page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        $documentRequests = [];
        if ($employee) {
            $documentRequests = DocumentRequestResource::collection(
                DocumentRequest::query()
                    ->forEmployee($employee->id)
                    ->latest()
                    ->get()
            )->resolve();
        }

        return Inertia::render('My/DocumentRequests/Index', [
            'hasEmployeeProfile' => $employee !== null,
            'documentRequests' => $documentRequests,
            'documentTypes' => DocumentRequestType::options(),
        ]);
    }
}
