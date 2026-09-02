<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiContext;

interface AiTool
{
    /** Unique tool name exposed to the model, e.g. get_students. */
    public function name(): string;

    /** Natural language description of what the tool returns. */
    public function description(): string;

    /** OpenAI-style JSON schema for the arguments object. */
    public function parameters(): array;

    /** Permission slug required to run this tool (owner is a superuser). */
    public function permission(): ?string;

    /** AI feature this tool belongs to (assistant / analytics / ...). */
    public function feature(): string;

    /** read = safe to auto-run when authorized; write = needs confirmation. */
    public function mode(): string;

    /**
     * Execute the tool against the existing business data for the tenant.
     *
     * @return array<string, mixed> small, structured result
     */
    public function handle(array $args, AiContext $context): array;
}
