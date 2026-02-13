<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Performance Management API (KPIs)
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users to manage KPI templates,
| assignments, and progress tracking for performance evaluations.
|
*/

Route::prefix('performance')->group(function () {
    // KPI Templates
    Route::get('/kpi-templates', [\App\Http\Controllers\Api\KpiTemplateController::class, 'index'])
        ->name('api.performance.kpi-templates.index');
    Route::post('/kpi-templates', [\App\Http\Controllers\Api\KpiTemplateController::class, 'store'])
        ->name('api.performance.kpi-templates.store');
    Route::get('/kpi-templates/{kpiTemplate}', [\App\Http\Controllers\Api\KpiTemplateController::class, 'show'])
        ->name('api.performance.kpi-templates.show');
    Route::put('/kpi-templates/{kpiTemplate}', [\App\Http\Controllers\Api\KpiTemplateController::class, 'update'])
        ->name('api.performance.kpi-templates.update');
    Route::delete('/kpi-templates/{kpiTemplate}', [\App\Http\Controllers\Api\KpiTemplateController::class, 'destroy'])
        ->name('api.performance.kpi-templates.destroy');

    // KPI Assignments
    Route::get('/kpi-assignments', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'index'])
        ->name('api.performance.kpi-assignments.index');
    Route::post('/kpi-assignments', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'store'])
        ->name('api.performance.kpi-assignments.store');
    Route::post('/kpi-assignments/bulk', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'bulkAssign'])
        ->name('api.performance.kpi-assignments.bulk');
    Route::get('/kpi-assignments/{kpiAssignment}', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'show'])
        ->name('api.performance.kpi-assignments.show');
    Route::put('/kpi-assignments/{kpiAssignment}', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'update'])
        ->name('api.performance.kpi-assignments.update');
    Route::delete('/kpi-assignments/{kpiAssignment}', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'destroy'])
        ->name('api.performance.kpi-assignments.destroy');
    Route::post('/kpi-assignments/{kpiAssignment}/progress', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'recordProgress'])
        ->name('api.performance.kpi-assignments.record-progress');
    Route::post('/kpi-assignments/{kpiAssignment}/complete', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'complete'])
        ->name('api.performance.kpi-assignments.complete');
    Route::get('/kpi-assignments/{kpiAssignment}/progress-history', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'progressHistory'])
        ->name('api.performance.kpi-assignments.progress-history');

    // Participant KPIs Summary
    Route::get('/participants/{participant}/kpis', [\App\Http\Controllers\Api\ParticipantKpiController::class, 'index'])
        ->name('api.performance.participants.kpis');

    // Competencies
    Route::get('/competencies', [\App\Http\Controllers\Api\CompetencyController::class, 'index'])
        ->name('api.performance.competencies.index');
    Route::post('/competencies', [\App\Http\Controllers\Api\CompetencyController::class, 'store'])
        ->name('api.performance.competencies.store');
    Route::get('/competencies/{competency}', [\App\Http\Controllers\Api\CompetencyController::class, 'show'])
        ->name('api.performance.competencies.show');
    Route::put('/competencies/{competency}', [\App\Http\Controllers\Api\CompetencyController::class, 'update'])
        ->name('api.performance.competencies.update');
    Route::delete('/competencies/{competency}', [\App\Http\Controllers\Api\CompetencyController::class, 'destroy'])
        ->name('api.performance.competencies.destroy');

    // Proficiency Levels
    Route::get('/proficiency-levels', [\App\Http\Controllers\Api\ProficiencyLevelController::class, 'index'])
        ->name('api.performance.proficiency-levels.index');
    Route::get('/proficiency-levels/{proficiencyLevel}', [\App\Http\Controllers\Api\ProficiencyLevelController::class, 'show'])
        ->name('api.performance.proficiency-levels.show');

    // Position Competencies (Matrix)
    Route::get('/position-competencies', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'index'])
        ->name('api.performance.position-competencies.index');
    Route::post('/position-competencies', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'store'])
        ->name('api.performance.position-competencies.store');
    Route::post('/position-competencies/batch', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'batchUpdate'])
        ->name('api.performance.position-competencies.batch');
    Route::get('/position-competencies/{positionCompetency}', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'show'])
        ->name('api.performance.position-competencies.show');
    Route::put('/position-competencies/{positionCompetency}', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'update'])
        ->name('api.performance.position-competencies.update');
    Route::delete('/position-competencies/{positionCompetency}', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'destroy'])
        ->name('api.performance.position-competencies.destroy');

    // Competency Evaluations
    Route::get('/competency-evaluations', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'index'])
        ->name('api.performance.competency-evaluations.index');
    Route::post('/competency-evaluations', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'store'])
        ->name('api.performance.competency-evaluations.store');
    Route::get('/competency-evaluations/{competencyEvaluation}', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'show'])
        ->name('api.performance.competency-evaluations.show');
    Route::put('/competency-evaluations/{competencyEvaluation}', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'update'])
        ->name('api.performance.competency-evaluations.update');
    Route::delete('/competency-evaluations/{competencyEvaluation}', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'destroy'])
        ->name('api.performance.competency-evaluations.destroy');
    Route::post('/competency-evaluations/{competencyEvaluation}/self-rating', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'submitSelfRating'])
        ->name('api.performance.competency-evaluations.self-rating');
    Route::post('/competency-evaluations/{competencyEvaluation}/manager-rating', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'submitManagerRating'])
        ->name('api.performance.competency-evaluations.manager-rating');

    // Participant Competency Evaluations
    Route::get('/participants/{participant}/competency-evaluations', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'participantEvaluations'])
        ->name('api.performance.participants.competency-evaluations');

    // Goals Management
    Route::apiResource('goals', \App\Http\Controllers\Api\GoalController::class)
        ->names([
            'index' => 'api.performance.goals.index',
            'store' => 'api.performance.goals.store',
            'show' => 'api.performance.goals.show',
            'update' => 'api.performance.goals.update',
            'destroy' => 'api.performance.goals.destroy',
        ]);
    Route::post('/goals/{goal}/submit-approval', [\App\Http\Controllers\Api\GoalController::class, 'submitForApproval'])
        ->name('api.performance.goals.submit-approval');
    Route::post('/goals/{goal}/approve', [\App\Http\Controllers\Api\GoalController::class, 'approve'])
        ->name('api.performance.goals.approve');
    Route::post('/goals/{goal}/reject', [\App\Http\Controllers\Api\GoalController::class, 'reject'])
        ->name('api.performance.goals.reject');
    Route::post('/goals/{goal}/progress', [\App\Http\Controllers\Api\GoalController::class, 'updateProgress'])
        ->name('api.performance.goals.progress');
    Route::post('/goals/{goal}/complete', [\App\Http\Controllers\Api\GoalController::class, 'complete'])
        ->name('api.performance.goals.complete');

    // Development Plans Management
    Route::post('/development-plans/{developmentPlan}/approve', [\App\Http\Controllers\Performance\DevelopmentPlanController::class, 'approve'])
        ->name('api.performance.development-plans.approve');
    Route::post('/development-plans/{developmentPlan}/reject', [\App\Http\Controllers\Performance\DevelopmentPlanController::class, 'reject'])
        ->name('api.performance.development-plans.reject');

    // Goal Key Results (nested under goals)
    Route::get('/goals/{goal}/key-results', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'index'])
        ->name('api.performance.goals.key-results.index');
    Route::post('/goals/{goal}/key-results', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'store'])
        ->name('api.performance.goals.key-results.store');
    Route::get('/goals/{goal}/key-results/{keyResult}', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'show'])
        ->name('api.performance.goals.key-results.show');
    Route::put('/goals/{goal}/key-results/{keyResult}', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'update'])
        ->name('api.performance.goals.key-results.update');
    Route::delete('/goals/{goal}/key-results/{keyResult}', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'destroy'])
        ->name('api.performance.goals.key-results.destroy');
    Route::post('/goals/{goal}/key-results/{keyResult}/progress', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'recordProgress'])
        ->name('api.performance.goals.key-results.progress');

    // Goal Milestones (nested under goals)
    Route::get('/goals/{goal}/milestones', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'index'])
        ->name('api.performance.goals.milestones.index');
    Route::post('/goals/{goal}/milestones', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'store'])
        ->name('api.performance.goals.milestones.store');
    Route::get('/goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'show'])
        ->name('api.performance.goals.milestones.show');
    Route::put('/goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'update'])
        ->name('api.performance.goals.milestones.update');
    Route::delete('/goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'destroy'])
        ->name('api.performance.goals.milestones.destroy');
    Route::post('/goals/{goal}/milestones/{milestone}/toggle', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'toggleComplete'])
        ->name('api.performance.goals.milestones.toggle');

    // Goal Comments (nested under goals)
    Route::get('/goals/{goal}/comments', [\App\Http\Controllers\Api\GoalCommentController::class, 'index'])
        ->name('api.performance.goals.comments.index');
    Route::post('/goals/{goal}/comments', [\App\Http\Controllers\Api\GoalCommentController::class, 'store'])
        ->name('api.performance.goals.comments.store');
    Route::get('/goals/{goal}/comments/{comment}', [\App\Http\Controllers\Api\GoalCommentController::class, 'show'])
        ->name('api.performance.goals.comments.show');
    Route::put('/goals/{goal}/comments/{comment}', [\App\Http\Controllers\Api\GoalCommentController::class, 'update'])
        ->name('api.performance.goals.comments.update');
    Route::delete('/goals/{goal}/comments/{comment}', [\App\Http\Controllers\Api\GoalCommentController::class, 'destroy'])
        ->name('api.performance.goals.comments.destroy');
});

/*
|--------------------------------------------------------------------------
| Employee Goal Self-Service API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('my')->group(function () {
    Route::get('/goals', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'index'])
        ->name('api.my.goals.index');
    Route::post('/goals', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'store'])
        ->name('api.my.goals.store');
    Route::get('/goals/statistics', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'statistics'])
        ->name('api.my.goals.statistics');
    Route::get('/goals/{goal}', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'show'])
        ->name('api.my.goals.show');
    Route::put('/goals/{goal}', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'update'])
        ->name('api.my.goals.update');
    Route::post('/goals/{goal}/progress', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'updateProgress'])
        ->name('api.my.goals.progress');
    Route::post('/goals/{goal}/submit-approval', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'submitForApproval'])
        ->name('api.my.goals.submit-approval');
    Route::delete('/goals/{goal}', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'destroy'])
        ->name('api.my.goals.destroy');

    // Development Plans Self-Service API
    Route::post('/development-plans', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'store'])
        ->name('api.my.development-plans.store');
    Route::put('/development-plans/{developmentPlan}', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'update'])
        ->name('api.my.development-plans.update');
    Route::post('/development-plans/{developmentPlan}/submit', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'submit'])
        ->name('api.my.development-plans.submit');
    Route::post('/development-plans/{developmentPlan}/items', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'addItem'])
        ->name('api.my.development-plans.add-item');
    Route::put('/development-plans/{developmentPlan}/items/{item}', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'updateItem'])
        ->name('api.my.development-plans.update-item');
    Route::post('/development-plans/{developmentPlan}/items/{item}/activities', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'addActivity'])
        ->name('api.my.development-plans.add-activity');
    Route::put('/development-plans/activities/{activity}', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'updateActivity'])
        ->name('api.my.development-plans.update-activity');
    Route::post('/development-plans/activities/{activity}/complete', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'completeActivity'])
        ->name('api.my.development-plans.complete-activity');
    Route::post('/development-plans/{developmentPlan}/check-ins', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'addCheckIn'])
        ->name('api.my.development-plans.add-check-in');
});

/*
|--------------------------------------------------------------------------
| Manager Goal API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('manager')->group(function () {
    Route::get('/team-goals', [\App\Http\Controllers\Api\TeamGoalController::class, 'index'])
        ->name('api.manager.team-goals.index');
    Route::get('/team-goals/pending-approvals', [\App\Http\Controllers\Api\TeamGoalController::class, 'pendingApprovals'])
        ->name('api.manager.team-goals.pending-approvals');
    Route::get('/team-goals/summary', [\App\Http\Controllers\Api\TeamGoalController::class, 'summary'])
        ->name('api.manager.team-goals.summary');
    Route::post('/team-goals/{goal}/approve', [\App\Http\Controllers\Api\TeamGoalController::class, 'approve'])
        ->name('api.manager.team-goals.approve');
    Route::post('/team-goals/{goal}/reject', [\App\Http\Controllers\Api\TeamGoalController::class, 'reject'])
        ->name('api.manager.team-goals.reject');
});
