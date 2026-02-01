<?php

namespace App\Http\Controllers\Api;

use App\Enums\JobLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PositionController extends Controller
{
    /**
     * Display a listing of positions with optional filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Position::query()
            ->with('salaryGrade');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by job level
        if ($request->filled('job_level')) {
            $jobLevel = JobLevel::tryFrom($request->input('job_level'));
            if ($jobLevel) {
                $query->where('job_level', $jobLevel);
            }
        }

        // Filter by salary grade
        if ($request->filled('salary_grade_id')) {
            $query->where('salary_grade_id', $request->input('salary_grade_id'));
        }

        $positions = $query->orderBy('title')->get();

        return PositionResource::collection($positions);
    }

    /**
     * Store a newly created position.
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $position = Position::create($request->validated());

        $position->load('salaryGrade');

        return (new PositionResource($position))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified position.
     */
    public function show(Position $position): PositionResource
    {
        Gate::authorize('can-manage-organization');

        $position->load('salaryGrade');

        return new PositionResource($position);
    }

    /**
     * Update the specified position.
     */
    public function update(UpdatePositionRequest $request, Position $position): PositionResource
    {
        Gate::authorize('can-manage-organization');

        $position->update($request->validated());

        $position->load('salaryGrade');

        return new PositionResource($position);
    }

    /**
     * Remove the specified position.
     */
    public function destroy(Position $position): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $position->delete();

        return response()->json([
            'message' => 'Position deleted successfully.',
        ]);
    }
}
