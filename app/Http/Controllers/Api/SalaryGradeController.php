<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalaryGradeRequest;
use App\Http\Requests\UpdateSalaryGradeRequest;
use App\Http\Resources\SalaryGradeResource;
use App\Models\SalaryGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SalaryGradeController extends Controller
{
    /**
     * Display a listing of salary grades.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $salaryGrades = SalaryGrade::query()
            ->with(['steps', 'positions'])
            ->orderBy('name')
            ->get();

        return SalaryGradeResource::collection($salaryGrades);
    }

    /**
     * Store a newly created salary grade with optional steps.
     */
    public function store(StoreSalaryGradeRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $steps = $validated['steps'] ?? [];
        unset($validated['steps']);

        $salaryGrade = DB::transaction(function () use ($validated, $steps) {
            $salaryGrade = SalaryGrade::create($validated);

            foreach ($steps as $stepData) {
                $salaryGrade->steps()->create($stepData);
            }

            return $salaryGrade;
        });

        $salaryGrade->load('steps');

        return (new SalaryGradeResource($salaryGrade))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified salary grade.
     */
    public function show(SalaryGrade $salaryGrade): SalaryGradeResource
    {
        Gate::authorize('can-manage-organization');

        $salaryGrade->load(['steps', 'positions']);

        return new SalaryGradeResource($salaryGrade);
    }

    /**
     * Update the specified salary grade with inline steps management.
     */
    public function update(UpdateSalaryGradeRequest $request, SalaryGrade $salaryGrade): SalaryGradeResource
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $steps = $validated['steps'] ?? [];
        unset($validated['steps']);

        DB::transaction(function () use ($salaryGrade, $validated, $steps) {
            $salaryGrade->update($validated);

            // Replace all steps with the new ones
            $salaryGrade->steps()->delete();

            foreach ($steps as $stepData) {
                $salaryGrade->steps()->create($stepData);
            }
        });

        $salaryGrade->load('steps');

        return new SalaryGradeResource($salaryGrade);
    }

    /**
     * Remove the specified salary grade.
     */
    public function destroy(SalaryGrade $salaryGrade): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check if positions are referencing this grade
        if ($salaryGrade->positions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete salary grade because it is referenced by positions.',
            ], 422);
        }

        DB::transaction(function () use ($salaryGrade) {
            // Delete associated steps first
            $salaryGrade->steps()->delete();
            $salaryGrade->delete();
        });

        return response()->json([
            'message' => 'Salary grade deleted successfully.',
        ]);
    }
}
