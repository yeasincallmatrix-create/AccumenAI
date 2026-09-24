<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourseCategory extends Model
{
    use TenantScoped;

    protected $table = 'training_course_categories';

    public $timestamps = true;

    protected $guarded = [];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(TrainingCourse::class, 'category_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(TrainingSubject::class, 'category_id');
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(TrainingCourseSubCategory::class, 'category_id');
    }
}
