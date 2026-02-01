<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDocumentRequestRequest;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Notifications\DocumentRequestStatusUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DocumentRequestAdminController extends Controller
{
    /**
     * Update a document request's status and admin notes.
     */
    public function update(UpdateDocumentRequestRequest $request, string $tenant, DocumentRequest $documentRequest): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();

        $documentRequest->status = $validated['status'];
        $documentRequest->admin_notes = $validated['admin_notes'] ?? $documentRequest->admin_notes;

        if (DocumentRequestStatus::from($validated['status']) === DocumentRequestStatus::Processing) {
            $documentRequest->processed_at = now();
        }

        if (DocumentRequestStatus::from($validated['status']) === DocumentRequestStatus::Collected) {
            $documentRequest->collected_at = now();
        }

        $documentRequest->save();

        $userId = $documentRequest->employee->user_id;
        if ($userId) {
            User::find($userId)?->notify(
                new DocumentRequestStatusUpdated($documentRequest)
            );
        }

        return response()->json([
            'message' => 'Document request updated successfully.',
            'document_request' => $documentRequest,
        ]);
    }
}
