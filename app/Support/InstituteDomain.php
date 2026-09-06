<?php

namespace App\Support;

use App\Models\Institute;

/**
 * Authoritative institute domain resolver — server-side only.
 *
 * Academic: Education + {school, college, polytechnic, university}
 * Professional: Training Center + {training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center}
 * Medical (Phase 0 — HMS Foundation): Healthcare + {hospital, clinic, pharmacy, diagnostic_center}
 * Other: everything else (retail, manufacturing, service, transportation, restaurant...)
 *
 * Never trust client-supplied subject_type / domain / category_id.
 */
final class InstituteDomain
{
    public const ACADEMIC = 'academic';
    public const PROFESSIONAL = 'professional';
    public const MEDICAL = 'medical';
    public const OTHER = 'other';

    /** Academic sub_industries under Education — all 8 from config/industry_rules.php:global.education */
    public const ACADEMIC_TYPES = [
        'school',
        'college',
        'polytechnic',
        'university',
        'madrasha',
        'primary_school',
        'secondary_high_school',
        'school_college',
    ];

    /** Professional sub_industries under Training Center — all 16 from config/industry_rules.php:global.training_center */
    public const PROFESSIONAL_TYPES = [
        'training_institute',
        'professional_training_center',
        'dance_academy',
        'it_training_center',
        'vocational_training_center',
        'institution',
        'professional_training_academy',
        'computer_it_training_institute',
        'vocational_institute',
        'technical_training_center',
        'skill_development_center',
        'martial_arts',
        'music_academy',
        'sports_academy',
        'language_academy',
        'coaching_centre',
    ];

    /**
     * Medical sub_industries under Healthcare — canonical keys from
     * config/industry_rules.php:global.healthcare.
     *
     * Phase 0 — HMS Foundation. Legacy alias `diagnostic` is normalized
     * to `diagnostic_center` in normalizeSubIndustry().
     */
    public const MEDICAL_TYPES = [
        'hospital',
        'clinic',
        'diagnostic_center',
        'pharmacy',
    ];

    /** Medical industry key (canonical taxonomy). */
    public const MEDICAL_INDUSTRY = 'healthcare';

    /**
     * Canonical industry keys for other domains (no academic/professional/medical structure exposed).
     */
    public const OTHER_INDUSTRIES = [
        'retail', 'manufacturing', 'service', 'transportation', 'restaurant',
        // finance etc. pass through as OTHER — they get no domain gateway
    ];

    /**
     * Resolve domain from stored institute row. Normalizes legacy keys.
     */
    public static function fromInstitute(?Institute $institute): string
    {
        if ($institute === null) {
            return self::OTHER;
        }
        return self::fromKeys((string) ($institute->industry ?? ''), (string) ($institute->sub_industry ?? ''));
    }

    public static function fromKeys(string $industry, string $subIndustry): string
    {
        $industry = strtolower(trim($industry));
        $sub = strtolower(trim($subIndustry));

        // Normalize legacy aliases before domain check
        $industry = self::normalizeIndustry($industry);
        $sub = self::normalizeSubIndustry($industry, $sub);

        if ($industry === 'education' && in_array($sub, self::ACADEMIC_TYPES, true)) {
            return self::ACADEMIC;
        }
        if ($industry === 'training_center' && in_array($sub, self::PROFESSIONAL_TYPES, true)) {
            return self::PROFESSIONAL;
        }
        // Phase 0 — HMS Foundation: healthcare industry resolves to medical
        if ($industry === self::MEDICAL_INDUSTRY && in_array($sub, self::MEDICAL_TYPES, true)) {
            return self::MEDICAL;
        }

        return self::OTHER;
    }

    /**
     * Domain map grouped by domain (Phase 0 compat helper for the HMS module).
     *
     * Note: authoritative resolution stays in fromKeys()/fromInstitute();
     * this is a descriptive map only.
     */
    public static function domains(): array
    {
        return [
            'academic' => array_merge(['education'], self::ACADEMIC_TYPES),
            'professional' => array_merge(['training_center'], self::PROFESSIONAL_TYPES),
            'medical' => array_merge([self::MEDICAL_INDUSTRY], self::MEDICAL_TYPES),
            'other' => self::OTHER_INDUSTRIES,
        ];
    }

    /**
     * Phase 0 compat: resolve a domain from a single industry string.
     *
     * Accepts either a canonical industry key ('healthcare' → medical,
     * 'education' → academic, 'training_center' → professional) or a bare
     * sub_industry slug ('hospital' → medical, 'school' → academic, ...).
     * An optional $subIndustry narrows ambiguous cases via fromKeys().
     */
    public static function getDomain(string $industry, ?string $subIndustry = null): string
    {
        $industry = strtolower(trim($industry));

        if ($subIndustry !== null && $subIndustry !== '') {
            return self::fromKeys($industry, $subIndustry);
        }

        if ($industry === self::MEDICAL_INDUSTRY || in_array($industry, self::MEDICAL_TYPES, true)) {
            return self::MEDICAL;
        }
        if ($industry === 'education' || in_array($industry, self::ACADEMIC_TYPES, true)) {
            return self::ACADEMIC;
        }
        if ($industry === 'training_center' || in_array($industry, self::PROFESSIONAL_TYPES, true)) {
            return self::PROFESSIONAL;
        }

        return self::OTHER;
    }

    public static function isMedical(?Institute $institute): bool
    {
        return self::fromInstitute($institute) === self::MEDICAL;
    }

    public static function isAcademic(?Institute $institute): bool
    {
        return self::fromInstitute($institute) === self::ACADEMIC;
    }

    public static function isProfessional(?Institute $institute): bool
    {
        return self::fromInstitute($institute) === self::PROFESSIONAL;
    }

    /** Whether the (industry, sub) combo is valid per canonical taxonomy */
    public static function isValidCombination(string $industry, ?string $sub): bool
    {
        $industry = self::normalizeIndustry(strtolower(trim($industry)));
        $sub = $sub !== null ? self::normalizeSubIndustry($industry, strtolower(trim($sub))) : null;

        $allIndustries = array_keys(config('industry_rules.global.industries', []));
        if (! in_array($industry, $allIndustries, true)) {
            return false;
        }
        // Industries without sub_industries must have sub null/empty
        $subs = IndustryRules::subIndustries('', $industry);
        if ($subs === []) {
            return $sub === null || $sub === '';
        }
        // Must be in that industry's sub list (after normalization)
        $rawSubs = IndustryRules::subIndustries('', $industry);
        $normalizedKeys = array_map(fn($k) => self::normalizeSubIndustry($industry, $k), array_keys($rawSubs));
        return $sub !== null && in_array($sub, $normalizedKeys, true);
    }

    /** Subject type derived from domain: academic|professional — for other returns professional as safe default */
    public static function subjectTypeFor(?Institute $institute): string
    {
        $domain = self::fromInstitute($institute);
        if ($domain === self::ACADEMIC) return 'academic';
        if ($domain === self::PROFESSIONAL) return 'professional';
        // other industries: default professional (they should not use subject master academically)
        return 'professional';
    }

    /** Normalize legacy industry aliases to canonical keys */
    public static function normalizeIndustry(string $industry): string
    {
        $map = [
            'transport' => 'transportation',
        ];
        return $map[$industry] ?? $industry;
    }

    /** Normalize legacy sub_industry aliases to canonical slugs */
    public static function normalizeSubIndustry(string $industry, string $sub): string
    {
        if ($sub === '' || $sub === null) return $sub;
        $map = [
            // education legacy training types should already be training_center; but handle if seen under education
            'institution' => 'training_institute',
            'professional_training_academy' => 'professional_training_center',
            'computer_it_training_institute' => 'it_training_center',
            'computer_it' => 'it_training_center',
            'vocational_institute' => 'vocational_training_center',
            'skill_development_center' => 'vocational_training_center',
            'technical_training_center' => 'vocational_training_center',
            // Phase 0 — HMS Foundation: healthcare spelling aliases only.
            // Distinct facility types (medical_college, nursing_home, dental,
            // eye/cardiac hospitals) are NOT remapped — they stay OTHER until
            // the taxonomy gains them as canonical healthcare sub-industries.
            'diagnostic' => 'diagnostic_center',
            'diagnostic_centre' => 'diagnostic_center',
        ];
        // Only apply institution-type renames when domain matches; but globally safe for now
        return $map[$sub] ?? $sub;
    }

    /**
     * Check if institute has meaningful domain-sensitive data that would block a domain switch.
     */
    public static function hasDomainData(int $instituteId): bool
    {
        // Check cheapest tables first; short-circuit on any hit
        if (\Illuminate\Support\Facades\DB::table('courses')->where('institute_id', $instituteId)->exists()) return true;
        if (\Illuminate\Support\Facades\DB::table('subjects')->where('institute_id', $instituteId)->exists()) return true;
        if (\Illuminate\Support\Facades\DB::table('course_curricula')->where('institute_id', $instituteId)->exists()) return true;
        if (\Illuminate\Support\Facades\DB::table('batches')->where('institute_id', $instituteId)->exists()) return true;
        if (\Illuminate\Support\Facades\DB::table('student_academic_placements')->where('institute_id', $instituteId)->exists()) return true;
        if (\Illuminate\Support\Facades\DB::table('academic_assessments')->where('institute_id', $instituteId)->exists()) return true;
        if (\Illuminate\Support\Facades\DB::table('academic_final_results')->where('institute_id', $instituteId)->exists()) return true;
        if (\Illuminate\Support\Facades\DB::table('academic_student_marks')->whereExists(function($q) use ($instituteId) {
            $q->select(\Illuminate\Support\Facades\DB::raw(1))->from('academic_assessments as aa')
              ->whereColumn('aa.id', 'academic_student_marks.academic_assessment_id')
              ->where('aa.institute_id', $instituteId);
        })->exists()) return true;
        // Phase 0 — HMS Foundation: medical data also blocks a domain switch.
        foreach (['patients', 'wards', 'appointments', 'admissions', 'medicines', 'prescriptions', 'lab_orders', 'invoices'] as $medicalTable) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable($medicalTable)
                    && \Illuminate\Support\Facades\DB::table($medicalTable)->where('institute_id', $instituteId)->exists()) {
                    return true;
                }
            } catch (\Throwable $_) {
                // Table missing (migration not yet run) — treat as no data.
            }
        }

        return false;
    }
}
