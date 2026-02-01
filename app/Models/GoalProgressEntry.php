<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GoalProgressEntry model for tracking progress updates on goals and key results.
 */
class GoalProgressEntry extends TenantModel
{
    /** @use HasFactory<\Database\Factories\GoalProgressEntryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'goal_id',
        'goal_key_result_id',
        'progress_value',
        'progress_percentage',
        'notes',
        'recorded_at',
        'recorded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress_value' => 'decimal:2',
            'progress_percentage' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Get the goal this progress entry belongs to.
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Get the key result this progress entry belongs to (if applicable).
     */
    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(GoalKeyResult::class, 'goal_key_result_id');
    }

    /**
     * Get the user who recorded this progress entry.
     */
    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
