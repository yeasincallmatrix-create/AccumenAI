<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;

class PhysiotherapyExercise extends Model
{
    use SoftDeletes;

    protected $table = 'physiotherapy_exercises';

    protected $fillable = [
        'institute_id', 'name', 'category', 'body_area',
        'description', 'instructions',
        'default_reps', 'default_sets', 'default_hold_seconds',
        'difficulty', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_reps' => 'integer',
        'default_sets' => 'integer',
        'default_hold_seconds' => 'integer',
    ];

    public const CATEGORIES = [
        'stretching' => 'Stretching',
        'strengthening' => 'Strengthening',
        'mobility' => 'Mobility',
        'balance' => 'Balance',
        'cardio' => 'Cardiovascular',
        'functional' => 'Functional',
    ];

    public const BODY_AREAS = [
        'shoulder' => 'Shoulder',
        'elbow' => 'Elbow',
        'wrist' => 'Wrist',
        'hip' => 'Hip',
        'knee' => 'Knee',
        'ankle' => 'Ankle',
        'lower_back' => 'Lower Back',
        'neck' => 'Neck',
        'full_body' => 'Full Body',
    ];

    public const DIFFICULTIES = [
        'easy' => 'Easy',
        'medium' => 'Medium',
        'hard' => 'Hard',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByBodyArea($q, string $area)
    {
        return $q->where('body_area', $area);
    }

    public function scopeByCategory($q, string $category)
    {
        return $q->where('category', $category);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function bodyAreaLabel(): string
    {
        return self::BODY_AREAS[$this->body_area] ?? $this->body_area;
    }

    public function difficultyLabel(): string
    {
        return self::DIFFICULTIES[$this->difficulty] ?? $this->difficulty;
    }
}
