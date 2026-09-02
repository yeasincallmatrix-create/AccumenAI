<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Contracts\AiProviderResponse;
use App\Support\AiConfig;
use Illuminate\Support\Facades\Http;

class CustomAiProvider implements AiProvider
{
    public function chat(array $messages, array $tools): AiProviderResponse
    {
        $payload = [
            'model' => AiConfig::model(),
            'messages' => $messages,
            'max_tokens' => AiConfig::maxTokens(),
            'temperature' => AiConfig::temperature(),
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(
                fn ($tool) => ['type' => 'function', 'function' => $tool],
                $tools
            );
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken(AiConfig::apiKey())
            ->acceptJson()
            ->timeout(AiConfig::timeout())
            ->post(rtrim(AiConfig::baseUrl(), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('AI provider error ('.$response->status().'): '.$response->body());
        }

        $data = $response->json();

        $message = $data['choices'][0]['message'] ?? [];
        $usage = $data['usage'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $toolCalls[] = [
                'name' => $call['function']['name'] ?? '',
                'arguments' => $call['function']['arguments'] ?? '{}',
                'id' => $call['id'] ?? '',
            ];
        }

        return new AiProviderResponse(
            content: (string) ($message['content'] ?? ''),
            toolCalls: $toolCalls,
            tokens: (int) (($usage['total_tokens'] ?? $usage['completion_tokens'] ?? 0)),
        );
    }
}
