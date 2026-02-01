<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkSchedule model for managing work schedule configurations.
 *
 * Supports Fixed, Flexible, Shifting, and Compressed schedule types
 * with configurable overtime rules and night differential settings.
 */
class WorkSchedule extends TenantModel
{
    /** @use HasFactory<\Database\Factories\WorkScheduleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'schedule_type',
        'description',
        'status',
        'time_configuration',
        'overtime_rules',
        'night_differential',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule_type' => ScheduleType::class,
            'time_configuration' => 'array',
            'overtime_rules' => 'array',
            'night_differential' => 'array',
        ];
    }

    /**
     * Get the employee schedule assignments for this work schedule.
     */
    public function employeeScheduleAssignments(): HasMany
    {
        return $this->hasMany(EmployeeScheduleAssignment::class);
    }

    /**
     * Scope to get only active work schedules.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
