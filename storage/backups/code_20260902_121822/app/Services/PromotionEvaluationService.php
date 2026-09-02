<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\PromotionPolicy;
use App\Models\PromotionPolicyRule;

/**
 * Promotion evaluation (Step 11): rules → per-student verdicts.
 *
 * Purely derived and read-only. Inputs come ONLY from a PUBLISHED result's
 * frozen snapshot (academic_final_result_students + academic_final_result_rows)
 * — never from a live recalculation.
 *
 * Semantics: each active rule (in display_order) yields pass_action when its
 * condition holds and fail_action otherwise. The student's verdict is the
 * most severe action across all rules, so configurable multi-branch rules
 * (e.g. "0 failed → promoted, 1 failed → conditional, 2+ → repeat") are built
 * from several ordered rules instead of a scripting language.
 */
class PromotionEvaluationService
{
    /**
     * Severity ladder for combining per-rule verdicts (higher = stricter).
     */
    private const SEVERITY = [
        PromotionPolicyRule::ACTION_PROMOTED => 0,
        PromotionPolicyRule::ACTION_COMPLETED => 0,
        PromotionPolicyRule::ACTION_GRADUATED => 0,
        PromotionPolicyRule::ACTION_CONDITIONAL => 1,
        PromotionPolicyRule::ACTION_NOT_PROMOTED => 2,
        PromotionPolicyRule::ACTION_REPEAT => 3,
    ];

    public const STATUS_INCOMPLETE_LIKE = [
        'incomplete',
        'absent_only',
        'not_eligible',
        'no_grade_scale',
        'no_band',
    ];

    /**
     * Evaluate a policy against a published result snapshot.
     *
     * @return array<int, array<string, mixed>> per placement:
     *                                          placement_id, student, input{...}, decision, reasons[]
     */
    public function evaluatePolicy(PromotionPolicy $policy, AcademicFinalResult $result): array
    {
        $rules = $policy->activeRules->all();
        $rowsByPlacement = $result->rows->groupBy('placement_id');

        $rows = [];
        foreach ($result->students as $student) {
            $placementId = (int) $student->placement_id;
            $input = $this->inputForStudent($student, $rowsByPlacement->get($placementId)?->all() ?? []);
            [$decision, $reasons] = $this->evaluate($rules, $input);

            $rows[] = [
                'placement_id' => $placementId,
                'student' => $student->placement?->student,
                'input' => $input,
                'decision' => $decision,
                'reasons' => $reasons,
            ];
        }

        return $rows;
    }

    /**
     * The per-student metrics derived from the published snapshot.
     *
     * @return array<string, mixed>
     */
    public function inputForStudent(AcademicFinalResultStudent $student, array $subjectRows): array
    {
        $failed = 0;
        $passed = 0;
        $incomplete = 0;
        $mandatoryPass = true;
        $mandatoryBlocked = null;

        foreach ($subjectRows as $row) {
            if (! $row instanceof AcademicFinalResultRow) {
                continue;
            }

            if ($row->subject_status === AcademicFinalResultService::SUBJECT_STATUS_FAIL) {
                $failed++;
            } elseif ($row->subject_status === AcademicFinalResultService::SUBJECT_STATUS_PASS) {
                $passed++;
            }

            if (in_array($row->status, self::STATUS_INCOMPLETE_LIKE, true)) {
                $incomplete++;
            }

            if (! (bool) $row->optional && $row->subject_status !== AcademicFinalResultService::SUBJECT_STATUS_PASS) {
                $mandatoryPass = false;
                $mandatoryBlocked = $mandatoryBlocked ?? $row->subject->name ?? ('Subject #'.$row->subject_id);
            }
        }

        return [
            'gpa' => $student->gpa,
            'gpa_status' => $student->gpa_status ?? AcademicFinalResultStudent::GPA_UNAVAILABLE,
            'gpa_mode' => $student->gpa_mode,
            'gpa_reason' => $student->gpa_reason,
            'passed_count' => $passed,
            'failed_count' => $failed,
            'incomplete_count' => $incomplete,
            'overall_pass' => $failed === 0 && $incomplete === 0,
            'mandatory_pass' => $mandatoryPass,
            'mandatory_blocked_subject' => $mandatoryBlocked,
        ];
    }

    /**
     * @param  array<int, PromotionPolicyRule>  $rules
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: array<int, string>}
     */
    private function evaluate(array $rules, array $input): array
    {
        $decision = PromotionPolicyRule::ACTION_PROMOTED;
        $severity = self::SEVERITY[$decision];
        $reasons = [];

        foreach ($rules as $rule) {
            $holds = $this->ruleHolds($rule, $input);
            $action = $holds ? $rule->pass_action : $rule->fail_action;
            $reasons[] = $this->describe($rule, $holds, $input);

            $actionSeverity = self::SEVERITY[$action] ?? self::SEVERITY[PromotionPolicyRule::ACTION_REPEAT];
            if ($actionSeverity > $severity) {
                $severity = $actionSeverity;
                $decision = $action;
            }
        }

        return [$decision, $reasons];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function ruleHolds(PromotionPolicyRule $rule, array $input): bool
    {
        return match ($rule->rule_type) {
            PromotionPolicyRule::RULE_OVERALL_PASS => (bool) $input['overall_pass'],
            PromotionPolicyRule::RULE_MANDATORY_PASS => (bool) $input['mandatory_pass'],
            PromotionPolicyRule::RULE_GPA_THRESHOLD => $this->compareGpa($rule, $input),
            default => $this->compareCount($rule, $input),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function compareGpa(PromotionPolicyRule $rule, array $input): bool
    {
        if ($input['gpa_status'] !== AcademicFinalResultStudent::GPA_COMPUTED || $input['gpa'] === null) {
            return false;
        }

        return $this->compare((float) $input['gpa'], $rule->operator ?? '>=', (float) $rule->value);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function compareCount(PromotionPolicyRule $rule, array $input): bool
    {
        $field = $rule->field ?? PromotionPolicyRule::FIELD_FAILED_COUNT;
        $actual = match ($field) {
            PromotionPolicyRule::FIELD_INCOMPLETE_COUNT => (float) $input['incomplete_count'],
            default => (float) $input['failed_count'],
        };

        return $this->compare($actual, $rule->operator ?? '>=', (float) $rule->value);
    }

    private function compare(float $actual, string $operator, float $value): bool
    {
        return match ($operator) {
            '>=' => $actual >= $value,
            '>' => $actual > $value,
            '<=' => $actual <= $value,
            '<' => $actual < $value,
            '==' => $actual == $value,
            '!=' => $actual != $value,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function describe(PromotionPolicyRule $rule, bool $holds, array $input): string
    {
        $verdict = $holds ? $rule->pass_action : $rule->fail_action;

        return match ($rule->rule_type) {
            PromotionPolicyRule::RULE_OVERALL_PASS => 'Overall result '.($holds ? 'passed' : 'not passed').' → '.$verdict,
            PromotionPolicyRule::RULE_MANDATORY_PASS => $holds
                ? 'All mandatory subjects passed → '.$verdict
                : 'Mandatory subject "'.($input['mandatory_blocked_subject'] ?? 'unknown').'" not passed → '.$verdict,
            PromotionPolicyRule::RULE_GPA_THRESHOLD => 'GPA '.($input['gpa_status'] === AcademicFinalResultStudent::GPA_COMPUTED ? $input['gpa'] : 'unavailable').' '.($rule->operator ?? '>=').' '.$rule->value.' → '.$verdict,
            default => ucfirst(str_replace('_', ' ', (string) ($rule->field ?? 'failed_count'))).' '.($input[$rule->field === PromotionPolicyRule::FIELD_INCOMPLETE_COUNT ? 'incomplete_count' : 'failed_count'] ?? 0).' '.($rule->operator ?? '>=').' '.$rule->value.' → '.$verdict,
        };
    }
}
