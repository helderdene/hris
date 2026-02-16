<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Employee model for digital 201 file management.
 *
 * Extends TenantModel for multi-tenant database isolation.
 * Includes personal info, employment details, government IDs, and status tracking.
 */
class Employee extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // Core identification
        'user_id',
        'employee_number',

        // Personal info
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'civil_status',
        'nationality',
        'fathers_name',
        'mothers_name',

        // Government IDs
        'tin',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'umid',
        'passport_number',
        'drivers_license',
        'nbi_clearance',
        'police_clearance',
        'prc_license',

        // Employment relationships
        'department_id',
        'position_id',
        'work_location_id',
        'supervisor_id',

        // Employment details
        'employment_type',
        'employment_status',
        'hire_date',
        'regularization_date',
        'termination_date',
        'basic_salary',
        'pay_frequency',

        // JSON fields
        'address',
        'emergency_contact',
        'education',
        'work_history',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'regularization_date' => 'date',
            'termination_date' => 'date',
            'basic_salary' => 'decimal:2',
            'employment_type' => EmploymentType::class,
            'employment_status' => EmploymentStatus::class,
            'address' => 'array',
            'emergency_contact' => 'array',
            'education' => 'array',
            'work_history' => 'array',
        ];
    }

    /**
     * Get the department the employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position the employee holds.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the work location where the employee is assigned.
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * Get the associated user account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the employee's supervisor.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * Get the employees that report to this employee.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    /**
     * Get the employee's status history records.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(EmployeeStatusHistory::class);
    }

    /**
     * Get the employee's assignment history records.
     */
    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(EmployeeAssignmentHistory::class);
    }

    /**
     * Get the employee's compensation record.
     */
    public function compensation(): HasOne
    {
        return $this->hasOne(EmployeeCompensation::class);
    }

    /**
     * Get the employee's compensation history records.
     */
    public function compensationHistory(): HasMany
    {
        return $this->hasMany(CompensationHistory::class);
    }

    /**
     * Get the employee's documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the employee's device sync records.
     */
    public function deviceSyncs(): HasMany
    {
        return $this->hasMany(EmployeeDeviceSync::class);
    }

    /**
     * Get the employee's device sync logs.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(DeviceSyncLog::class);
    }

    /**
     * Get the employee's attendance logs.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * Get the employee's daily time records.
     */
    public function dailyTimeRecords(): HasMany
    {
        return $this->hasMany(DailyTimeRecord::class);
    }

    /**
     * Get the employee's loans.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    /**
     * Get the employee's payroll entries.
     */
    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * Get the employee's payroll adjustments.
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(EmployeeAdjustment::class);
    }

    /**
     * Get the employee's BIR 2316 certificates.
     */
    public function bir2316Certificates(): HasMany
    {
        return $this->hasMany(Bir2316Certificate::class);
    }

    /**
     * Get the employee's schedule assignments.
     */
    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(EmployeeScheduleAssignment::class);
    }

    /**
     * Get the employee's leave balances.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get the employee's leave applications.
     */
    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    /**
     * Get the employee's overtime requests.
     */
    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    /**
     * Get leave applications pending this employee's approval.
     */
    public function pendingLeaveApprovals(): HasMany
    {
        return $this->hasMany(LeaveApplicationApproval::class, 'approver_employee_id')
            ->where('decision', \App\Enums\LeaveApprovalDecision::Pending);
    }

    /**
     * Get the employee's goals.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Get the employee's active goals.
     */
    public function activeGoals(): HasMany
    {
        return $this->hasMany(Goal::class)->active();
    }

    /**
     * Get the employee's development plans.
     */
    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class);
    }

    /**
     * Get the employee's probationary evaluations.
     */
    public function probationaryEvaluations(): HasMany
    {
        return $this->hasMany(ProbationaryEvaluation::class);
    }

    /**
     * Get probationary evaluations where this employee is the evaluator (manager).
     */
    public function probationaryEvaluationsAsEvaluator(): HasMany
    {
        return $this->hasMany(ProbationaryEvaluation::class, 'evaluator_id');
    }

    /**
     * Get pending probationary evaluation approvals for this employee (HR role).
     */
    public function pendingProbationaryApprovals(): HasMany
    {
        return $this->hasMany(ProbationaryEvaluationApproval::class, 'approver_employee_id')
            ->where('decision', \App\Enums\ProbationaryApprovalDecision::Pending);
    }

    /**
     * Get the employee's training session enrollments.
     */
    public function trainingEnrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    /**
     * Get the employee's active training enrollments.
     */
    public function activeTrainingEnrollments(): HasMany
    {
        return $this->trainingEnrollments()->active();
    }

    /**
     * Get the employee's training waitlist entries.
     */
    public function trainingWaitlists(): HasMany
    {
        return $this->hasMany(TrainingWaitlist::class);
    }

    /**
     * Get the employee's active waitlist entries.
     */
    public function activeTrainingWaitlists(): HasMany
    {
        return $this->trainingWaitlists()->waiting();
    }

    /**
     * Get the employee's certifications.
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    /**
     * Get the employee's compliance training assignments.
     */
    public function complianceAssignments(): HasMany
    {
        return $this->hasMany(ComplianceAssignment::class);
    }

    /**
     * Get the employee's active compliance training assignments.
     */
    public function activeComplianceAssignments(): HasMany
    {
        return $this->complianceAssignments()->active();
    }

    /**
     * Get the employee's overdue compliance training assignments.
     */
    public function overdueComplianceAssignments(): HasMany
    {
        return $this->complianceAssignments()->overdue();
    }

    /**
     * Get the employee's compliance certificates.
     */
    public function complianceCertificates(): HasMany
    {
        return $this->hasMany(ComplianceCertificate::class, 'compliance_assignment_id')
            ->whereHas('complianceAssignment', function ($query) {
                $query->where('employee_id', $this->id);
            });
    }

    /**
     * Get the chain of supervisors for this employee.
     *
     * @param  int  $maxLevels  Maximum levels to traverse up the chain
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    public function getSupervisorChain(int $maxLevels = 3): \Illuminate\Support\Collection
    {
        $chain = collect();
        $current = $this->supervisor;
        $level = 0;

        while ($current !== null && $level < $maxLevels) {
            // Prevent infinite loops from circular references
            if ($chain->contains('id', $current->id)) {
                break;
            }

            $chain->push($current);
            $current = $current->supervisor;
            $level++;
        }

        return $chain;
    }

    /**
     * Get the employee's profile photo document.
     */
    public function getProfilePhoto(): ?Document
    {
        return $this->documents()
            ->whereHas('category', function ($query) {
                $query->where('name', 'Profile Photo');
            })
            ->latest()
            ->first();
    }

    /**
     * Get the biometric devices this employee should sync to.
     *
     * Returns devices at the employee's work location.
     *
     * @return SupportCollection<int, BiometricDevice>
     */
    public function getDevicesToSyncTo(): SupportCollection
    {
        if ($this->work_location_id === null) {
            return collect();
        }

        return BiometricDevice::query()
            ->where('work_location_id', $this->work_location_id)
            ->active()
            ->get();
    }

    /**
     * Get the employee's current (active) assignments.
     *
     * @return SupportCollection<int, EmployeeAssignmentHistory>
     */
    public function currentAssignments(): SupportCollection
    {
        return $this->assignmentHistory()->current()->get();
    }

    /**
     * Scope to get only active employees.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', EmploymentStatus::Active);
    }

    /**
     * Get the employee's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $parts = array_filter([
                    $this->first_name,
                    $this->middle_name,
                    $this->last_name,
                    $this->suffix,
                ]);

                return implode(' ', $parts);
            }
        );
    }

    /**
     * Get the employee's initials from first and last name.
     */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $firstInitial = $this->first_name ? strtoupper(substr($this->first_name, 0, 1)) : '';
                $lastInitial = $this->last_name ? strtoupper(substr($this->last_name, 0, 1)) : '';

                return $firstInitial.$lastInitial;
            }
        );
    }

    /**
     * Get the employee's age in years.
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                if (! $this->date_of_birth) {
                    return null;
                }

                return $this->date_of_birth->age;
            }
        );
    }

    /**
     * Get the employee's years of service.
     */
    protected function yearsOfService(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                if (! $this->hire_date) {
                    return 0;
                }

                return $this->hire_date->diffInYears(now());
            }
        );
    }

    /**
     * Get the employee's tenure in months.
     */
    public function getTenureInMonths(): int
    {
        if (! $this->hire_date) {
            return 0;
        }

        return (int) $this->hire_date->diffInMonths(now());
    }

    /**
     * Get the employee's tenure in years.
     */
    public function getTenureInYears(): int
    {
        if (! $this->hire_date) {
            return 0;
        }

        return (int) $this->hire_date->diffInYears(now());
    }
}
