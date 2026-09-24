<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourseSubCategory extends Model
{
    use TenantScoped;

    protected $table = 'training_course_sub_categories';

    public $timestamps = true;

    protected $guarded = [];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrainingCourseCategory::class, 'category_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(TrainingCourse::class, 'sub_category_id');
    }
}
