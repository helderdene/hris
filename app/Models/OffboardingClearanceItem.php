<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company property or accountability the employee must clear before leaving.
 */
class OffboardingClearanceItem extends TenantModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_offboarding_id',
        'name',
        'remarks',
        'cleared_at',
        'cleared_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cleared_at' => 'datetime',
        ];
    }

    /**
     * Get the offboarding this item belongs to.
     */
    public function offboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOffboarding::class, 'employee_offboarding_id');
    }

    /**
     * Get the user who cleared this item.
     */
    public function clearedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
