<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A panelist assigned to an interview.
 */
class InterviewPanelist extends TenantModel
{
    /** @use HasFactory<\Database\Factories\InterviewPanelistFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'interview_id',
        'employee_id',
        'is_lead',
        'invitation_sent_at',
        'feedback',
        'rating',
        'feedback_submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'invitation_sent_at' => 'datetime',
            'feedback_submitted_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    /**
     * Get the interview.
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * Get the employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
