<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ProficiencyLevel model for storing the 1-5 rating scale definitions.
 *
 * Each level represents a stage of competency development with
 * descriptive name, explanation, and behavioral indicators.
 */
class ProficiencyLevel extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ProficiencyLevelFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'level',
        'name',
        'description',
        'behavioral_indicators',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'behavioral_indicators' => 'array',
        ];
    }

    /**
     * Get the proficiency level by its numeric value.
     */
    public static function findByLevel(int $level): ?self
    {
        return static::where('level', $level)->first();
    }

    /**
     * Get all levels ordered by level number.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function ordered(): \Illuminate\Database\Eloquent\Collection
    {
        return static::orderBy('level')->get();
    }
}
