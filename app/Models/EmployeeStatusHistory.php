<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmployeeStatusHistory model for tracking employee status changes over time.
 *
 * Only one active status at a time per employee (ended_at is null for current status).
 */
class EmployeeStatusHistory extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeStatusHistoryFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'employee_status_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'previous_status',
        'new_status',
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
            'effective_date' => 'date',
            'ended_at' => 'datetime',
            'previous_status' => EmploymentStatus::class,
            'new_status' => EmploymentStatus::class,
        ];
    }

    /**
     * Get the employee this status history belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get only current (active) status records.
     *
     * Current status records have ended_at as null.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
