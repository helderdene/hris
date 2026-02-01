<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Department model for organizational hierarchy.
 *
 * Supports hierarchical parent-child relationships through self-referential parent_id.
 * Soft deletes preserve referential integrity.
 */
class Department extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DepartmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'description',
        'department_head_id',
        'status',
    ];

    /**
     * Get the parent department.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Get the child departments.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /**
     * Get the department head employee.
     */
    public function departmentHead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'department_head_id');
    }

    /**
     * Get the employees belonging to this department.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get job postings for this department.
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * Get the department head history records.
     */
    public function headHistory(): HasMany
    {
        return $this->hasMany(DepartmentHeadHistory::class);
    }

    /**
     * Scope to get only active departments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only root departments (no parent).
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Validate that setting a parent_id would not create a circular reference.
     *
     * Returns true if the parent_id is valid (no circular reference),
     * false if it would create a circular reference.
     *
     * @param  int|null  $newParentId  The proposed parent ID
     */
    public function validateNotCircularReference(?int $newParentId): bool
    {
        // Null parent is always valid
        if ($newParentId === null) {
            return true;
        }

        // Cannot set self as parent
        if ($this->id !== null && $newParentId === $this->id) {
            return false;
        }

        // Check if the new parent is a descendant of this department
        if ($this->id !== null && $this->isDescendant($newParentId)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a department is a descendant of this department.
     */
    protected function isDescendant(int $departmentId): bool
    {
        $children = $this->children()->get();

        foreach ($children as $child) {
            if ($child->id === $departmentId) {
                return true;
            }

            if ($child->isDescendant($departmentId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all descendants of this department.
     *
     * @return \Illuminate\Support\Collection<int, Department>
     */
    public function getAllDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();
        $children = $this->children()->get();

        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }
}
