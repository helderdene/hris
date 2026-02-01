<?php

namespace App\Models;

use App\Enums\ComplianceRuleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ComplianceAssignmentRule model for auto-assignment logic.
 *
 * Defines rules for automatically assigning compliance training to employees
 * based on department, position, job level, work location, or employment type.
 */
class ComplianceAssignmentRule extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ComplianceAssignmentRuleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_course_id',
        'name',
        'description',
        'rule_type',
        'conditions',
        'days_to_complete_override',
        'priority',
        'is_active',
        'apply_to_new_hires',
        'apply_to_existing',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rule_type' => ComplianceRuleType::class,
            'conditions' => 'array',
            'days_to_complete_override' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'apply_to_new_hires' => 'boolean',
            'apply_to_existing' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * Get the compliance course this rule belongs to.
     */
    public function complianceCourse(): BelongsTo
    {
        return $this->belongsTo(ComplianceCourse::class);
    }

    /**
     * Get the employee who created this rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Get the assignments created by this rule.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ComplianceAssignment::class, 'assignment_rule_id');
    }

    /**
     * Scope to get only active rules.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get rules effective on a given date.
     */
    public function scopeEffectiveOn(Builder $query, ?\DateTimeInterface $date = null): Builder
    {
        $date = $date ?? now();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('effective_until')
                ->orWhere('effective_until', '>=', $date);
        });
    }

    /**
     * Scope to get rules that apply to new hires.
     */
    public function scopeForNewHires(Builder $query): Builder
    {
        return $query->where('apply_to_new_hires', true);
    }

    /**
     * Scope to get rules ordered by priority.
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    /**
     * Scope to filter by rule type.
     */
    public function scopeOfType(Builder $query, ComplianceRuleType|string $type): Builder
    {
        $value = $type instanceof ComplianceRuleType ? $type->value : $type;

        return $query->where('rule_type', $value);
    }

    /**
     * Check if the rule is currently effective.
     */
    public function isEffective(): bool
    {
        $now = now();

        if ($this->effective_from && $now->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_until && $now->gt($this->effective_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this rule applies to the given employee.
     */
    public function appliesToEmployee(Employee $employee): bool
    {
        if (! $this->is_active || ! $this->isEffective()) {
            return false;
        }

        if ($this->rule_type === ComplianceRuleType::AllEmployees) {
            return true;
        }

        $conditions = $this->conditions ?? [];
        $conditionField = $this->rule_type->conditionField();

        if (! $conditionField || ! isset($conditions[$conditionField])) {
            return false;
        }

        $allowedValues = $conditions[$conditionField];
        $employeeAttribute = $this->rule_type->employeeAttribute();

        if (! $employeeAttribute) {
            return false;
        }

        $employeeValue = $employee->{$employeeAttribute};

        // Handle enum values
        if (is_object($employeeValue) && method_exists($employeeValue, 'value')) {
            $employeeValue = $employeeValue->value;
        }

        return in_array($employeeValue, $allowedValues, false);
    }

    /**
     * Get the days to complete for assignments created by this rule.
     */
    public function getDaysToComplete(): int
    {
        return $this->days_to_complete_override
            ?? $this->complianceCourse->days_to_complete
            ?? 30;
    }
}
