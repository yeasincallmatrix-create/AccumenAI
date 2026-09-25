<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Exam-to-exam weighting for batch results ("1st exam + nth exam weighted").
 *
 * When any exam in the batch carries a weight, the batch result percentage is
 *   Σ(exam % × weight) ÷ Σ(weight)   (normalized over the configured weights)
 * and unweighted exams (weight 0/null) do not count.
 * When no exam carries a weight, callers keep the historical plain marks sum.
 *
 * Written/Practical/Viva percents are within-exam component splits and are
 * intentionally unrelated to this weighting.
 */
class TrainingExamWeighting
{
    /**
     * Whether any exam in the batch carries a weight (weighted mode ON).
     *
     * @param  Collection<int, object{weight_percent: mixed}>  $exams
     */
    public static function enabled(Collection $exams): bool
    {
        foreach ($exams as $exam) {
            if ((float) ($exam->weight_percent ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Weighted summary across a batch's exams.
     *
     * @param  Collection<int, object{id: int|string, weight_percent: mixed, pass_marks: mixed}>  $exams  batch exams
     * @param  array<int, float>  $obtainedByExam  exam_id => obtained marks (0 counts; only keys present are counted)
     * @param  array<int, float>  $fullByExam  exam_id => full marks (>0)
     * @return array{percentage: float, pass_percentage: float, total_marks: float, obtained_marks: float}
     */
    public static function summarize(Collection $exams, array $obtainedByExam, array $fullByExam): array
    {
        $num = 0.0;
        $passNum = 0.0;
        $weightSum = 0.0;
        $total = 0.0;

        foreach ($exams as $exam) {
            $id = (int) $exam->id;
            if (! isset($fullByExam[$id]) || $fullByExam[$id] <= 0 || ! isset($obtainedByExam[$id])) {
                continue;
            }

            $weight = max(0.0, (float) ($exam->weight_percent ?? 0));
            if ($weight <= 0) {
                continue;
            }

            $full = (float) $fullByExam[$id];
            $percentage = min(100.0, max(0.0, $obtainedByExam[$id] / $full * 100));
            $passPercentage = min(100.0, max(0.0, (float) ($exam->pass_marks ?? 0) / $full * 100));

            $num += $percentage * $weight;
            $passNum += $passPercentage * $weight;
            $weightSum += $weight;
            $total += $full;
        }

        $percentage = $weightSum > 0 ? $num / $weightSum : 0.0;
        $passPercentage = $weightSum > 0 ? $passNum / $weightSum : 0.0;

        return [
            'percentage' => round($percentage, 2),
            'pass_percentage' => round($passPercentage, 2),
            'total_marks' => round($total, 2),
            'obtained_marks' => round($percentage / 100 * $total, 2),
        ];
    }
}
