<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitPreboardingItemRequest;
use App\Models\PreboardingChecklistItem;
use App\Services\PreboardingService;
use Illuminate\Http\JsonResponse;

class PreboardingSubmissionController extends Controller
{
    public function __construct(
        protected PreboardingService $preboardingService
    ) {}

    /**
     * Submit a preboarding checklist item.
     */
    public function store(SubmitPreboardingItemRequest $request, PreboardingChecklistItem $item): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $data['file'] = $request->file('file');
        }

        $item = $this->preboardingService->submitItem($item, $data);

        return response()->json([
            'message' => 'Item submitted successfully.',
            'item' => [
                'id' => $item->id,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'submitted_at' => $item->submitted_at?->format('M d, Y H:i'),
            ],
        ]);
    }
}
