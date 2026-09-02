<?php

namespace App\Support;

use App\Models\Institute;

/**
 * Authoritative institute domain resolver — server-side only.
 *
 * Academic: Education + {school, college, polytechnic, university}
 * Professional: Training Center + {training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center}
 * Other: everything else (retail, manufacturing, service, transportation, restaurant, healthcare...)
 *
 * Never trust client-supplied subject_type / domain / category_id.
 */
final class InstituteDomain
{
    public const ACADEMIC = 'academic';
    public const PROFESSIONAL = 'professional';
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
     * Canonical industry keys for other domains (no academic/professional structure exposed).
     */
    public const OTHER_INDUSTRIES = [
        'retail', 'manufacturing', 'service', 'transportation', 'restaurant',
        // also treat healthcare, finance, etc. as OTHER - they pass through but get no academic/professional gateway
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
        return self::OTHER;
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
        return false;
    }
}
