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
        'relation_to_primary',
        'primary_contact_id',
        'is_dependent',
        'is_patient',
        'guardian_name',
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
        'is_dependent' => 'boolean',
        'is_patient' => 'boolean',
    ];

    /** Real patients (excludes guardian placeholder rows). */
    public function scopePatients($query)
    {
        return $query->where('is_patient', true);
    }

    /** Guardian placeholder rows (phone holders, not yet patients). */
    public function scopeGuardians($query)
    {
        return $query->where('is_patient', false);
    }

    public function primaryContact()
    {
        return $this->belongsTo(self::class, 'primary_contact_id');
    }

    public function dependents()
    {
        return $this->hasMany(self::class, 'primary_contact_id');
    }

    /**
     * Compact age for family lists: "3y", "8mo", "5d", or null.
     */
    public function getShortAgeAttribute(): ?string
    {
        if (! $this->date_of_birth) {
            return null;
        }
        $days = $this->date_of_birth->diffInDays(now());
        if ($days < 30) {
            return $days.'d';
        }
        if ($days < 365) {
            return (int) floor($days / 30).'mo';
        }

        return $this->date_of_birth->age.'y';
    }

    /**
     * "Rahim (Son, 3y M)" — relation + age + gender initial.
     */
    public function getFamilyLabelAttribute(): string
    {
        $parts = [];
        if ($this->relation_to_primary && $this->relation_to_primary !== 'Self') {
            $parts[] = $this->relation_to_primary;
        } else {
            $parts[] = 'Self';
        }
        $bits = [];
        if ($this->short_age) {
            $bits[] = $this->short_age;
        }
        $bits[] = match ($this->gender) {
            'male' => 'M',
            'female' => 'F',
            default => 'O',
        };
        $parts[] = implode(' ', $bits);

        return $this->full_name.' ('.implode(', ', $parts).')';
    }

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

    public function problems()
    {
        return $this->hasMany(PatientProblem::class);
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
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
        $stored = \App\Services\Medical\NumberSequenceService::expandShortYears((string) $search);

        return $query->where(function ($q) use ($search, $stored) {
            $q->where('mr_number', 'LIKE', "%{$search}%");
            if ($stored !== (string) $search) {
                $q->orWhere('mr_number', 'LIKE', "%{$stored}%");
            }
            $q->orWhere('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }
}
