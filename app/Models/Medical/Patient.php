<?php

namespace App\Models\Medical;

use App\Models\Concerns\NormalizesPersonNames;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use NormalizesPersonNames;
    use SoftDeletes;

    protected $table = 'patients';

    protected $fillable = [
        'institute_id',
        'mr_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'phone',
        'email',
        'present_address',
        'present_country_id',
        'present_admin_1_id',
        'present_admin_2_id',
        'present_admin_3_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'blood_group',
        'allergies',
        'chronic_conditions',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function structuredAllergies()
    {
        return $this->hasMany(PatientAllergy::class);
    }

    /**
     * Mirror the free-text allergies column into structured rows (additive
     * only — never deletes, so verified clinical rows are never lost).
     */
    public function syncStructuredAllergies(): void
    {
        $names = array_values(array_unique(array_filter(array_map(
            fn ($v) => mb_substr(trim((string) $v), 0, 160),
            explode(',', (string) ($this->allergies ?? ''))
        ))));
        if ($names === []) {
            return;
        }

        $existing = $this->structuredAllergies()->pluck('allergen_name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->all();

        foreach ($names as $name) {
            if (in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }
            $this->structuredAllergies()->create([
                'institute_id' => $this->institute_id,
                'medicine_id' => null,
                'allergen_type' => 'drug',
                'allergen_name' => $name,
                'reaction' => null,
                'severity' => 'moderate',
                'is_verified' => false,
            ]);
            $existing[] = mb_strtolower($name);
        }
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    public function getAgeCategoryAttribute(): ?string
    {
        return mawa_age_category($this->date_of_birth);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Phase 02: keyword alternatives are grouped so a chained
     * where('institute_id', ...) can never be escaped by the ORs.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('mr_number', 'LIKE', "%{$search}%")
                ->orWhere('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }
}
