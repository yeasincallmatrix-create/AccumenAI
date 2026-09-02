<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiContext;
use App\Services\Ai\Contracts\AiTool;
use Illuminate\Support\Carbon;

abstract class AbstractAiTool implements AiTool
{
    public function feature(): string
    {
        return 'assistant';
    }

    public function mode(): string
    {
        return 'read';
    }

    public function permission(): ?string
    {
        return null;
    }

    protected function limit(array $args, int $default = 20, int $max = 50): int
    {
        $limit = (int) ($args['limit'] ?? $default);

        return max(1, min($limit, $max));
    }

    protected function dateArg(array $args, string $key): ?Carbon
    {
        $value = $args[$key] ?? null;

        return $value ? Carbon::parse($value)->startOfDay() : null;
    }

    protected function groupBy(array $args, array $allowed, string $default = 'none'): string
    {
        $group = (string) ($args['group_by'] ?? $default);

        return in_array($group, $allowed, true) ? $group : $default;
    }

    /**
     * @return array<string, mixed>
     */
    protected function result(array $summary, array $rows = [], array $meta = []): array
    {
        return array_merge($summary, $meta, ['rows' => $rows]);
    }

    /**
     * The branch the current actor is restricted to, or null for all branches.
     * Mirrors the BranchScoped global scope used by the web modules so the AI
     * inherits exactly the same branch restrictions as the UI.
     */
    protected function branchId(AiContext $context): ?int
    {
        return $context->branchId;
    }

    public function guard(AiContext $context): void
    {
        // Security: the tenant context must always be active when a tool runs,
        // otherwise a misbehaving request could leak another institute's rows.
        abort_if($context->tenantContextId() === null, 403, 'Tenant context is not active.');
    }
}
