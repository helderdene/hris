<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KpiProgressEntry model for tracking KPI progress history.
 *
 * Records progress updates with values, notes, and who recorded them.
 */
class KpiProgressEntry extends TenantModel
{
    /** @use HasFactory<\Database\Factories\KpiProgressEntryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kpi_assignment_id',
        'value',
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
            'value' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Get the KPI assignment this progress belongs to.
     */
    public function kpiAssignment(): BelongsTo
    {
        return $this->belongsTo(KpiAssignment::class);
    }

    /**
     * Get the user who recorded this progress.
     */
    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
