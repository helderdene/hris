<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmployeeScheduleAssignment model for assigning employees to work schedules.
 *
 * Supports effective dating for schedule changes with future-dated assignments.
 * One active schedule per employee at a time.
 */
class EmployeeScheduleAssignment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeScheduleAssignmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'work_schedule_id',
        'shift_name',
        'effective_date',
        'end_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Get the employee for this assignment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the work schedule for this assignment.
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * Scope to get only active assignments.
     *
     * Active assignments have:
     * - effective_date <= today
     * - end_date IS NULL OR end_date >= today
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('effective_date', '<=', now()->toDateString())
            ->where(function (Builder $query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }
}
