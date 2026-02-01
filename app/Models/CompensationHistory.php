<?php

namespace App\Models;

use App\Enums\PayType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CompensationHistory model for tracking employee compensation changes over time.
 *
 * Extends TenantModel for multi-tenant database isolation.
 * Uses ended_at pattern: null for current record, datetime for historical records.
 */
class CompensationHistory extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CompensationHistoryFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'compensation_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'previous_basic_pay',
        'new_basic_pay',
        'previous_pay_type',
        'new_pay_type',
        'effective_date',
        'changed_by',
        'remarks',
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
            'previous_basic_pay' => 'decimal:2',
            'new_basic_pay' => 'decimal:2',
            'previous_pay_type' => PayType::class,
            'new_pay_type' => PayType::class,
            'effective_date' => 'date',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this compensation history belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get only current (active) compensation history records.
     *
     * Current records have ended_at as null.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope to filter by employee ID.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
}
