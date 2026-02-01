<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DevelopmentPlanCheckIn model for recording development discussions.
 *
 * Provides a simple chronological log of check-in meetings
 * between employee and manager regarding the development plan.
 */
class DevelopmentPlanCheckIn extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DevelopmentPlanCheckInFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'development_plan_id',
        'check_in_date',
        'notes',
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
            'check_in_date' => 'date',
        ];
    }

    /**
     * Get the development plan this check-in belongs to.
     */
    public function developmentPlan(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPlan::class);
    }

    /**
     * Get the user who created this check-in.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
