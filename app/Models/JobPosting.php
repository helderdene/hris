<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\JobPostingStatus;
use App\Enums\SalaryDisplayOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * JobPosting model for public job listings.
 *
 * Supports a Draft -> Published -> Closed -> Archived lifecycle.
 */
class JobPosting extends TenantModel
{
    /** @use HasFactory<\Database\Factories\JobPostingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_requisition_id',
        'department_id',
        'position_id',
        'created_by_employee_id',
        'title',
        'slug',
        'description',
        'requirements',
        'benefits',
        'employment_type',
        'location',
        'salary_display_option',
        'salary_range_min',
        'salary_range_max',
        'application_instructions',
        'status',
        'published_at',
        'closed_at',
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
            'employment_type' => EmploymentType::class,
            'salary_display_option' => SalaryDisplayOption::class,
            'salary_range_min' => 'decimal:2',
            'salary_range_max' => 'decimal:2',
            'status' => JobPostingStatus::class,
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (JobPosting $posting) {
            if (empty($posting->slug)) {
                $posting->slug = self::generateUniqueSlug($posting->title);
            }

            if ($posting->status === null) {
                $posting->status = JobPostingStatus::Draft;
            }
        });
    }

    /**
     * Get the linked job requisition.
     */
    public function jobRequisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class);
    }

    /**
     * Get the department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the job applications for this posting.
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Get the employee who created this posting.
     */
    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id');
    }

    /**
     * Scope to only published postings.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', JobPostingStatus::Published);
    }

    /**
     * Scope to publicly visible postings (published).
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', JobPostingStatus::Published);
    }

    /**
     * Scope to filter by department.
     */
    public function scopeForDepartment(Builder $query, int|Department $department): Builder
    {
        $departmentId = $department instanceof Department ? $department->id : $department;

        return $query->where('department_id', $departmentId);
    }

    /**
     * Check if the posting can be edited.
     */
    protected function canBeEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeEdited()
        );
    }

    /**
     * Check if the posting can be published.
     */
    protected function canBePublished(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBePublished()
        );
    }

    /**
     * Check if the posting can be closed.
     */
    protected function canBeClosed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === JobPostingStatus::Published
        );
    }

    /**
     * Check if the posting is publicly visible.
     */
    protected function isPubliclyVisible(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->isPubliclyVisible()
        );
    }

    /**
     * Generate a unique slug from a title.
     */
    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
