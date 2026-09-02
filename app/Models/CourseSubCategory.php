<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSubCategory extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'course_sub_categories';

    public $timestamps = false;

    protected $guarded = [];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
