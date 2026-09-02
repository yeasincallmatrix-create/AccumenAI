<?php

namespace App\Services\Ai;

use App\Models\AiLog;
use App\Models\InstituteUser;
use App\Models\User;
use App\Support\AiConfig;

class AiLogger
{
    /**
     * @param  array{feature: string, prompt: string|null, tools: array<int, string>, status: string, tokens: int, error: string|null}  $data
     */
    public function log(array $data, AiContext $context): AiLog
    {
        $actor = $context->actor;
        $guard = $actor instanceof InstituteUser
            ? 'institute_user'
            : ($actor instanceof User ? 'web' : null);

        return AiLog::create([
            'institute_id' => $context->instituteId(),
            'user_type' => $guard,
            'user_id' => $actor?->id,
            'feature' => $data['feature'],
            'prompt' => AiConfig::storePrompts() ? $this->redact((string) ($data['prompt'] ?? '')) : null,
            'tools' => $data['tools'] ?? [],
            'status' => $data['status'] ?? 'ok',
            'tokens' => $data['tokens'] ?? 0,
            'error' => isset($data['error']) && $data['error'] !== null ? $this->redact((string) $data['error']) : null,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Mask common credential patterns so secrets never reach the audit log.
     */
    protected function redact(string $value): string
    {
        $patterns = [
            '/\bsk-[A-Za-z0-9_\-]{8,}\b/' => 'sk-***',
            '/\bBearer\s+[A-Za-z0-9._\-]{12,}\b/i' => 'Bearer ***',
            '/(password|passwd|secret|api[_-]?key|token)\s*[:=]\s*["\']?[^\s,;"\']+/i' => '$1: ***',
            '/\bAKIA[0-9A-Z]{16}\b/' => 'AKIA***',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }

        return $value;
    }
}
