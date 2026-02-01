<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document attached to a background check.
 */
class BackgroundCheckDocument extends TenantModel
{
    /** @use HasFactory<\Database\Factories\BackgroundCheckDocumentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'background_check_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /**
     * Get the background check.
     */
    public function backgroundCheck(): BelongsTo
    {
        return $this->belongsTo(BackgroundCheck::class);
    }
}
