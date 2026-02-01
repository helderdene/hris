<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\ComplianceAssignmentService;
use App\Services\ComplianceRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to evaluate compliance rules for a specific employee.
 */
class EvaluateComplianceRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Employee $employee,
        public bool $isNewHire = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ComplianceRuleEngine $ruleEngine,
        ComplianceAssignmentService $assignmentService
    ): void {
        // Get matching rules for this employee
        $matchingRules = $ruleEngine->getMatchingRules($this->employee);

        foreach ($matchingRules as $rule) {
            // Skip if the rule doesn't apply to this employee type
            if ($this->isNewHire && ! $rule->apply_to_new_hires) {
                continue;
            }

            if (! $this->isNewHire && ! $rule->apply_to_existing) {
                continue;
            }

            // Check if employee already has an active assignment for this course
            $hasActiveAssignment = $this->employee->complianceAssignments()
                ->where('compliance_course_id', $rule->compliance_course_id)
                ->active()
                ->exists();

            if ($hasActiveAssignment) {
                continue;
            }

            // Create the assignment
            $assignmentService->assignByRule($rule, $this->employee);
        }
    }
}
