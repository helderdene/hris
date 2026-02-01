<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DepartmentHeadHistory model for tracking department head changes over time.
 *
 * Only one active head at a time per department (ended_at is null for current head).
 */
class DepartmentHeadHistory extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DepartmentHeadHistoryFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'department_head_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'employee_id',
        'started_at',
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
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Get the department this history record belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Scope to get only current (active) head records.
     *
     * Current heads have ended_at as null.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
