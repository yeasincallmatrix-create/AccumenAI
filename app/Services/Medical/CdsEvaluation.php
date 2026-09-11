<?php

namespace App\Services\Medical;

/**
 * Phase 13 — result of one pure CDS evaluation.
 *
 * blocking[] / warnings[] carry finding payloads (rule, version, severity,
 * message, explanation, trigger). unevaluatedItemIndexes lists inputs the
 * engine could not resolve (free-text/unmapped — INSUFFICIENT_TERMINOLOGY,
 * never findings). errors[] records per-rule failures. An evaluation with
 * errors is NOT a clean bill of health — callers must surface that the
 * engine could not fully evaluate.
 */
final class CdsEvaluation
{
    /** @var array<int, array> */
    public array $blocking = [];

    /** @var array<int, array> */
    public array $warnings = [];

    /** @var array<int, int> */
    public array $unevaluatedItemIndexes = [];

    /** @var array<string, string> rule_key => error */
    public array $errors = [];

    public int $durationMs = 0;

    public readonly string $evaluatedAt;

    public function __construct(
        public readonly int $patientId,
        public readonly int $instituteId
    ) {
        $this->evaluatedAt = now()->toIso8601String();
    }

    public function addFinding(array $finding): void
    {
        if (($finding['block_policy'] ?? 'warn') === 'block') {
            $this->blocking[] = $finding;
        } else {
            $this->warnings[] = $finding;
        }
    }

    public function addError(string $ruleKey, string $message): void
    {
        $this->errors[$ruleKey] = mb_substr($message, 0, 500);
    }

    public function hasBlockingIssues(): bool
    {
        return $this->blocking !== [];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function findingCount(): int
    {
        return count($this->blocking) + count($this->warnings);
    }
}
