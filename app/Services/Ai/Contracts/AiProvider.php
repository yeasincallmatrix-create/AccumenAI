<?php

namespace App\Services\Ai\Contracts;

interface AiProvider
{
    /**
     * Send a chat request with optional function tools.
     *
     * @param  array<int, array<string, mixed>>  $messages  OpenAI-style messages
     * @param  array<int, array<string, mixed>>  $tools  OpenAI-style function definitions
     */
    public function chat(array $messages, array $tools): AiProviderResponse;
}
