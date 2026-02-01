<?php

namespace App\Models;

use App\Enums\AssignmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmployeeAssignmentHistory model for tracking employee assignment changes over time.
 *
 * Tracks changes to position, department, work location, and supervisor assignments.
 * Only one active assignment per type at a time per employee (ended_at is null for current assignment).
 */
class EmployeeAssignmentHistory extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeAssignmentHistoryFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'employee_assignment_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'assignment_type',
        'previous_value_id',
        'new_value_id',
        'effective_date',
        'remarks',
        'changed_by',
        'ended_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignment_type' => AssignmentType::class,
            'effective_date' => 'date',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this assignment history belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get only current (active) assignment records.
     *
     * Current assignment records have ended_at as null.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope to filter by assignment type.
     */
    public function scopeForType(Builder $query, AssignmentType $type): Builder
    {
        return $query->where('assignment_type', $type);
    }

    /**
     * Resolve the previous value name based on assignment type.
     */
    public function getPreviousValueNameAttribute(): ?string
    {
        if ($this->previous_value_id === null) {
            return null;
        }

        return $this->resolveValueName($this->previous_value_id);
    }

    /**
     * Resolve the new value name based on assignment type.
     */
    public function getNewValueNameAttribute(): ?string
    {
        return $this->resolveValueName($this->new_value_id);
    }

    /**
     * Resolve a value name from an ID based on the assignment type.
     */
    protected function resolveValueName(?int $valueId): ?string
    {
        if ($valueId === null) {
            return null;
        }

        return match ($this->assignment_type) {
            AssignmentType::Position => Position::find($valueId)?->title,
            AssignmentType::Department => Department::find($valueId)?->name,
            AssignmentType::Location => WorkLocation::find($valueId)?->name,
            AssignmentType::Supervisor => Employee::find($valueId)?->full_name,
            default => null,
        };
    }
}
