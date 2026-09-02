<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subject extends Model
{
    use SoftDeletes;

    protected $table = 'subjects';

    public $timestamps = true;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Subject $subject) {
            if (empty($subject->slug) && ! empty($subject->name)) {
                $base = Str::slug($subject->name) ?: 'subject-'.Str::random(6);
                $base = Str::limit($base, 170, '');
                $slug = $base;
                $suffix = 1;
                while (static::withTrashed()->where('institute_id', $subject->institute_id)->where('slug', $slug)->exists()) {
                    $suffix++;
                    $slug = Str::limit($base, 170 - strlen((string) $suffix) - 1, '').'-'.$suffix;
                }
                $subject->slug = $slug;
            }
        });

        static::updating(function (Subject $subject) {
            if ($subject->isDirty('name') && ! $subject->isDirty('slug')) {
                $base = Str::slug($subject->name) ?: $subject->slug;
                $base = Str::limit($base, 170, '');
                if ($base !== $subject->getOriginal('slug')) {
                    $slug = $base;
                    $suffix = 1;
                    while (static::withTrashed()->where('institute_id', $subject->institute_id)->where('slug', $slug)->where('id', '!=', $subject->id)->exists()) {
                        $suffix++;
                        $slug = Str::limit($base, 170 - strlen((string) $suffix) - 1, '').'-'.$suffix;
                    }
                    $subject->slug = $slug;
                }
            }
        });
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_subjects');
    }

    public function institutes(): BelongsToMany
    {
        return $this->belongsToMany(Institute::class, 'institute_subjects');
    }

    public function academicAssignments(): HasMany
    {
        return $this->hasMany(SubjectAcademicAssignment::class);
    }
}
