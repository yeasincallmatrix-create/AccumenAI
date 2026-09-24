<?php

namespace App\Support;

use App\Models\InstituteSetting;

/**
 * Training exam grade resolution.
 *
 * Grade bands come from institute_settings.training_config['gpa_model']
 * (the gear modal on training/exams): rows of grade / min_score / max_score
 * where the score is the marks percentage (obtained / full * 100).
 */
class TrainingGpa
{
    public const DEFAULT_BANDS = [
        ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100],
        ['grade' => 'A', 'min_score' => 70, 'max_score' => 79],
        ['grade' => 'B', 'min_score' => 60, 'max_score' => 69],
        ['grade' => 'C', 'min_score' => 50, 'max_score' => 59],
        ['grade' => 'F', 'min_score' => 0, 'max_score' => 49],
    ];

    /**
     * Bands configured for an institute (falls back to defaults when unset).
     *
     * @return array<int, array{grade: string, min_score: float, max_score: float}>
     */
    public static function bands(int $instituteId): array
    {
        $config = InstituteSetting::where('institute_id', $instituteId)->first()?->training_config ?? [];
        $rows = is_array($config['gpa_model'] ?? null) ? $config['gpa_model'] : [];

        if ($rows === []) {
            return self::DEFAULT_BANDS;
        }

        return array_values(array_map(fn ($row) => [
            'grade' => (string) ($row['grade'] ?? ''),
            'min_score' => (float) ($row['min_score'] ?? 0),
            'max_score' => (float) ($row['max_score'] ?? 0),
        ], $rows));
    }

    /**
     * Grade letter for obtained marks, or null when no band matches.
     *
     * @param  array<int, array{grade?: string, min_score?: float, max_score?: float}>  $bands
     */
    public static function grade(?float $obtained, ?float $fullMarks, array $bands): ?string
    {
        if ($obtained === null) {
            return null;
        }

        $full = (float) ($fullMarks ?? 0);
        $score = $full > 0 ? ($obtained / $full) * 100 : (float) $obtained;

        foreach ($bands as $band) {
            $min = (float) ($band['min_score'] ?? 0);
            $max = (float) ($band['max_score'] ?? 0);
            if ($score >= $min && $score <= $max) {
                $grade = trim((string) ($band['grade'] ?? ''));
                return $grade !== '' ? $grade : null;
            }
        }

        return null;
    }
}
