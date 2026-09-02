<?php

namespace App\Services\Ai\Contracts;

class AiProviderResponse
{
    /**
     * @param  array<int, array{name: string, arguments: string, id: string}>  $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls,
        public readonly int $tokens = 0,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
