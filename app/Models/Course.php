<?php

namespace App\Models;

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\CourseProviderType;
use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Course model for training course catalog.
 *
 * Stores course details including delivery method, provider info, prerequisites, and content.
 */
class Course extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'code',
        'description',
        'delivery_method',
        'provider_type',
        'provider_name',
        'duration_hours',
        'duration_days',
        'status',
        'level',
        'cost',
        'max_participants',
        'is_compliance',
        'learning_objectives',
        'syllabus',
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
            'delivery_method' => CourseDeliveryMethod::class,
            'provider_type' => CourseProviderType::class,
            'status' => CourseStatus::class,
            'level' => CourseLevel::class,
            'duration_hours' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_compliance' => 'boolean',
            'learning_objectives' => 'array',
        ];
    }

    /**
     * Get the employee who created this course.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Get the categories for this course.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CourseCategory::class, 'course_category_course')
            ->withTimestamps();
    }

    /**
     * Get the prerequisites for this course.
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'prerequisite_id')
            ->withPivot('is_mandatory')
            ->withTimestamps();
    }

    /**
     * Get the courses that require this course as a prerequisite.
     */
    public function requiredBy(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'prerequisite_id', 'course_id')
            ->withPivot('is_mandatory')
            ->withTimestamps();
    }

    /**
     * Get the materials for this course.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class)->ordered();
    }

    /**
     * Get the training sessions for this course.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * Get the upcoming training sessions for this course.
     */
    public function upcomingSessions(): HasMany
    {
        return $this->sessions()->upcoming()->scheduled();
    }

    /**
     * Get the compliance course settings (if this is a compliance course).
     */
    public function complianceCourse(): HasOne
    {
        return $this->hasOne(ComplianceCourse::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, CourseStatus|string $status): Builder
    {
        $value = $status instanceof CourseStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to get only published courses.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', CourseStatus::Published->value);
    }

    /**
     * Scope to get only draft courses.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', CourseStatus::Draft->value);
    }

    /**
     * Scope to get only compliance courses.
     */
    public function scopeCompliance(Builder $query): Builder
    {
        return $query->where('is_compliance', true);
    }

    /**
     * Scope to get only non-compliance courses.
     */
    public function scopeNonCompliance(Builder $query): Builder
    {
        return $query->where('is_compliance', false);
    }

    /**
     * Scope to filter by delivery method.
     */
    public function scopeByDeliveryMethod(Builder $query, CourseDeliveryMethod|string $method): Builder
    {
        $value = $method instanceof CourseDeliveryMethod ? $method->value : $method;

        return $query->where('delivery_method', $value);
    }

    /**
     * Scope to filter by provider type.
     */
    public function scopeByProviderType(Builder $query, CourseProviderType|string $type): Builder
    {
        $value = $type instanceof CourseProviderType ? $type->value : $type;

        return $query->where('provider_type', $value);
    }

    /**
     * Scope to filter by level.
     */
    public function scopeByLevel(Builder $query, CourseLevel|string $level): Builder
    {
        $value = $level instanceof CourseLevel ? $level->value : $level;

        return $query->where('level', $value);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeInCategory(Builder $query, int|CourseCategory $category): Builder
    {
        $categoryId = $category instanceof CourseCategory ? $category->id : $category;

        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('course_categories.id', $categoryId);
        });
    }

    /**
     * Scope to search by title or code.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        });
    }

    /**
     * Publish the course.
     */
    public function publish(): bool
    {
        $this->status = CourseStatus::Published;

        return $this->save();
    }

    /**
     * Archive the course.
     */
    public function archive(): bool
    {
        $this->status = CourseStatus::Archived;

        return $this->save();
    }

    /**
     * Check if the course is published.
     */
    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    /**
     * Check if the course is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === CourseStatus::Draft;
    }

    /**
     * Check if the course is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === CourseStatus::Archived;
    }

    /**
     * Check if the course is a compliance course.
     */
    public function isComplianceCourse(): bool
    {
        return $this->is_compliance === true;
    }

    /**
     * Get formatted duration string accessor.
     */
    public function getFormattedDurationAttribute(): ?string
    {
        $parts = [];

        if ($this->duration_days) {
            $dayPart = $this->duration_days.' '.($this->duration_days === 1 ? 'day' : 'days');
            if ($this->duration_hours) {
                $hourPart = ((int) $this->duration_hours).' '.((int) $this->duration_hours === 1 ? 'hour' : 'hours');
                $parts[] = "{$dayPart} ({$hourPart})";
            } else {
                $parts[] = $dayPart;
            }
        } elseif ($this->duration_hours) {
            $parts[] = ((int) $this->duration_hours).' '.((int) $this->duration_hours === 1 ? 'hour' : 'hours');
        }

        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    /**
     * Get the mandatory prerequisites.
     */
    public function getMandatoryPrerequisites(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->prerequisites()->wherePivot('is_mandatory', true)->get();
    }

    /**
     * Duplicate the course with a new code.
     */
    public function duplicate(string $newCode): self
    {
        $duplicate = $this->replicate(['code', 'status']);
        $duplicate->code = $newCode;
        $duplicate->status = CourseStatus::Draft;
        $duplicate->save();

        // Copy categories
        $duplicate->categories()->sync($this->categories->pluck('id'));

        // Copy prerequisites
        $prerequisiteData = [];
        foreach ($this->prerequisites as $prerequisite) {
            $prerequisiteData[$prerequisite->id] = ['is_mandatory' => $prerequisite->pivot->is_mandatory];
        }
        $duplicate->prerequisites()->sync($prerequisiteData);

        return $duplicate;
    }
}
