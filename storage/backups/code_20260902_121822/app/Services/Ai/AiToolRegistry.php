<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiTool;
use InvalidArgumentException;

/**
 * Registry of AI tools. Tools are resolved from config/ai-tools.php so new
 * industries / tools can be added without touching the AI engine.
 *
 * A tool is offered to the model only when ALL of these hold:
 *   - the tool belongs to the tenant's industry (or to the shared `core` list), AND
 *   - its feature is enabled for the tenant, AND
 *   - the actor has the required permission (owner is a superuser).
 */
class AiToolRegistry
{
    /** @var array<string, AiTool>|null keyed by tool name */
    private ?array $resolved = null;

    /** @var array<class-string, string>|null tool class => tool name */
    private ?array $nameByClass = null;

    /**
     * @return array<string, AiTool> keyed by tool name
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $this->resolved = [];
        $this->nameByClass = [];
        foreach (config('ai-tools', []) as $industry => $classes) {
            foreach ($classes as $class) {
                $tool = app($class);
                if (! $tool instanceof AiTool) {
                    throw new InvalidArgumentException("AI tool [{$class}] must implement AiTool.");
                }
                $this->resolved[$tool->name()] = $tool;
                $this->nameByClass[$class] = $tool->name();
            }
        }

        return $this->resolved;
    }

    public function get(string $name): ?AiTool
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * Tool classes configured for one industry (including shared core tools).
     *
     * @return array<int, class-string>
     */
    public function classesForIndustry(string $industry): array
    {
        return array_values(array_unique(array_merge(
            (array) config("ai-tools.{$industry}", []),
            (array) config('ai-tools.core', [])
        )));
    }

    /**
     * Tools available for the current tenant/actor context — filtered by the
     * tenant's industry, enabled features and the actor's permissions.
     *
     * @return array<string, AiTool>
     */
    public function available(AiContext $context): array
    {
        $this->all();

        $result = [];
        foreach ($this->classesForIndustry($context->industry) as $class) {
            $name = $this->nameByClass[$class] ?? null;
            if ($name === null) {
                continue;
            }
            $tool = $this->resolved[$name] ?? null;
            if ($tool === null) {
                continue;
            }
            if (! $this->isAvailable($tool, $context)) {
                continue;
            }
            $result[$name] = $tool;
        }

        return $result;
    }

    public function isAvailable(AiTool $tool, AiContext $context): bool
    {
        if (! $context->featureEnabled($tool->feature())) {
            return false;
        }

        if ($context->hasPermission('*')) {
            return true;
        }

        $permission = $tool->permission();

        return $permission === null || $context->hasPermission($permission);
    }

    /**
     * OpenAI-style function definitions for the model.
     *
     * @param  array<string, AiTool>  $tools
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    public function definitions(array $tools): array
    {
        $definitions = [];
        foreach ($tools as $tool) {
            $definitions[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ];
        }

        return $definitions;
    }
}
