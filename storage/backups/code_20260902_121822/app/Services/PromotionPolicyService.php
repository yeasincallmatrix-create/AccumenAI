<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\PromotionPolicy;
use App\Models\PromotionPolicyRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Promotion policy + rule configuration (Step 11).
 *
 * Security model mirrors the other academic services:
 *   - institute identity is passed in, never read from request input;
 *   - the academic year must belong to the institute (AcademyYear is
 *     institute-scoped master data);
 *   - the class must appear in the institute's effective (country + override)
 *     structure and the group must belong to that class;
 *   - policy branch_id stays NULL (whole-institute) — promotion policies are
 *     not branch-configurable in this phase; branch visibility of decisions is
 *     inherited from the final result / placement rows.
 *
 * Rules are a closed enum with controlled operators/values — never an
 * arbitrary scripting surface.
 */
class PromotionPolicyService
{
    public function __construct(private readonly AcademicSubjectService $subjects) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function storePolicy(Institute $institute, array $data, ?int $actorId = null): PromotionPolicy
    {
        $this->assertContext($institute, $data);

        return DB::transaction(function () use ($institute, $data, $actorId) {
            return PromotionPolicy::create([
                'institute_id' => $institute->id,
                'branch_id' => null,
                'name' => trim((string) $data['name']),
                'academic_year_id' => (int) $data['academic_year_id'],
                'class_grade_id' => (int) $data['class_grade_id'],
                'academic_group_id' => $data['academic_group_id'] !== null && $data['academic_group_id'] !== '' ? (int) $data['academic_group_id'] : null,
                'status' => $data['status'] ?? PromotionPolicy::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePolicy(Institute $institute, PromotionPolicy $policy, array $data): PromotionPolicy
    {
        abort_if((int) $policy->institute_id !== (int) $institute->id, 404, 'Policy does not belong to this institute.');

        $this->assertContext($institute, $data);

        $policy->update([
            'name' => trim((string) $data['name']),
            'academic_year_id' => (int) $data['academic_year_id'],
            'class_grade_id' => (int) $data['class_grade_id'],
            'academic_group_id' => $data['academic_group_id'] !== null && $data['academic_group_id'] !== '' ? (int) $data['academic_group_id'] : null,
            'status' => $data['status'] ?? $policy->status,
        ]);

        return $policy->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeRule(PromotionPolicy $policy, array $data): PromotionPolicyRule
    {
        $data = $this->validatedRule($data);

        return DB::transaction(function () use ($policy, $data) {
            return PromotionPolicyRule::create([
                'policy_id' => $policy->id,
                'rule_type' => $data['rule_type'],
                'field' => $data['field'] ?? null,
                'operator' => $data['operator'] ?? null,
                'value' => $data['value'] ?? null,
                'pass_action' => $data['pass_action'] ?? PromotionPolicyRule::ACTION_PROMOTED,
                'fail_action' => $data['fail_action'] ?? PromotionPolicyRule::ACTION_REPEAT,
                'display_order' => (int) ($data['display_order'] ?? 0),
                'status' => (bool) ($data['status'] ?? true),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRule(Institute $institute, PromotionPolicyRule $rule, array $data): PromotionPolicyRule
    {
        $this->assertRuleOwnership($institute, $rule);

        $data = $this->validatedRule($data);

        $rule->update([
            'rule_type' => $data['rule_type'],
            'field' => $data['field'] ?? null,
            'operator' => $data['operator'] ?? null,
            'value' => $data['value'] ?? null,
            'pass_action' => $data['pass_action'] ?? PromotionPolicyRule::ACTION_PROMOTED,
            'fail_action' => $data['fail_action'] ?? PromotionPolicyRule::ACTION_REPEAT,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => (bool) ($data['status'] ?? true),
        ]);

        return $rule->refresh();
    }

    public function destroyRule(Institute $institute, PromotionPolicyRule $rule): void
    {
        $this->assertRuleOwnership($institute, $rule);

        $rule->delete();
    }

    public function setStatus(PromotionPolicy $policy, string $status): PromotionPolicy
    {
        abort_if(! in_array($status, PromotionPolicy::STATUSES, true), 422, 'Invalid policy status.');

        $policy->update(['status' => $status]);

        return $policy->refresh();
    }

    // ------------------------------------------------------------- Internals

    /**
     * A promotion rule is owned by the institute that owns its parent policy.
     * The rule model is deliberately not TenantScoped (it rides on the policy),
     * so ownership must be re-established server-side before any mutation —
     * the actor's institute is passed in, never read from request input.
     */
    private function assertRuleOwnership(Institute $institute, PromotionPolicyRule $rule): void
    {
        $policy = PromotionPolicy::query()
            ->withoutGlobalScopes()
            ->whereKey($rule->policy_id)
            ->first(['id', 'institute_id']);

        abort_if(
            $policy === null || (int) $policy->institute_id !== (int) $institute->id,
            404,
            'Rule does not belong to this institute.'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertContext(Institute $institute, array $data): void
    {
        $year = AcademicYear::query()->where('institute_id', $institute->id)->find((int) ($data['academic_year_id'] ?? 0));
        if ($year === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Invalid academic year for this institute.',
            ]);
        }

        $classGrade = null;
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            if ((int) $entry['class_grade']->id === (int) ($data['class_grade_id'] ?? 0)) {
                $classGrade = $entry['class_grade'];
                break;
            }
        }
        if ($classGrade === null) {
            throw ValidationException::withMessages([
                'class_grade_id' => 'Invalid class / grade for this institute.',
            ]);
        }

        $groupId = $data['academic_group_id'] ?? null;
        if (filled($groupId)) {
            $group = $classGrade->groups()->where('status', true)->find((int) $groupId);
            if ($group === null) {
                throw ValidationException::withMessages([
                    'academic_group_id' => 'Invalid group / stream for the selected class.',
                ]);
            }
        }
    }

    /**
     * Validate a rule payload. Boolean rule types ignore field/operator/value;
     * numeric rule types compare a controlled `field` against `value` with a
     * controlled `operator`. Actions come from the closed outcome enum.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedRule(array $data): array
    {
        $ruleType = $data['rule_type'] ?? null;
        abort_if(! in_array($ruleType, PromotionPolicyRule::RULE_TYPES, true), 422, 'Invalid rule type.');

        $validated = [
            'rule_type' => $ruleType,
            'pass_action' => $data['pass_action'] ?? PromotionPolicyRule::ACTION_PROMOTED,
            'fail_action' => $data['fail_action'] ?? PromotionPolicyRule::ACTION_REPEAT,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => (bool) ($data['status'] ?? true),
        ];

        abort_if(! in_array($validated['pass_action'], PromotionPolicyRule::ACTIONS, true), 422, 'Invalid pass action.');
        abort_if(! in_array($validated['fail_action'], PromotionPolicyRule::ACTIONS, true), 422, 'Invalid fail action.');

        if (in_array($ruleType, [PromotionPolicyRule::RULE_OVERALL_PASS, PromotionPolicyRule::RULE_MANDATORY_PASS], true)) {
            return $validated;
        }

        $field = $data['field'] ?? match ($ruleType) {
            PromotionPolicyRule::RULE_GPA_THRESHOLD => PromotionPolicyRule::FIELD_GPA,
            PromotionPolicyRule::RULE_CONDITIONAL => PromotionPolicyRule::FIELD_FAILED_COUNT,
            default => PromotionPolicyRule::FIELD_FAILED_COUNT,
        };

        abort_if(! in_array($field, PromotionPolicyRule::FIELDS, true), 422, 'Invalid rule field.');

        $operator = $data['operator'] ?? '>=';
        abort_if(! in_array($operator, PromotionPolicyRule::OPERATORS, true), 422, 'Invalid rule operator.');

        $value = $data['value'] ?? null;
        abort_if($value === null || ! is_numeric($value), 422, 'Rule value must be numeric.');

        if (in_array($field, [PromotionPolicyRule::FIELD_FAILED_COUNT, PromotionPolicyRule::FIELD_INCOMPLETE_COUNT], true)) {
            abort_if((float) $value < 0 || (float) $value !== floor((float) $value), 422, 'Subject-count rule values must be non-negative integers.');
        }

        return [
            ...$validated,
            'field' => $field,
            'operator' => $operator,
            'value' => (string) $value,
        ];
    }
}
