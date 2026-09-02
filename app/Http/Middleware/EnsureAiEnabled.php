<?php

namespace App\Http\Middleware;

use App\Models\Institute;
use App\Support\AiConfig;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates any route behind the AI layer. Requires the platform-level AI switch AND,
 * for institute requests, the institute's own AI toggle + the requested feature.
 */
class EnsureAiEnabled
{
    public function handle(Request $request, Closure $next, string $feature = 'assistant'): mixed
    {
        if (! AiConfig::enabled()) {
            abort(403, 'AI is not enabled on this platform.');
        }

        if (TenantContext::id() !== null) {
            $institute = Institute::query()->withoutGlobalScopes()->find(TenantContext::id());
            if ($institute === null) {
                abort(403, 'Institute not found.');
            }

            $config = $institute->settings?->ai_config ?? [];
            if (! ($config['enabled'] ?? false)) {
                abort(403, 'AI is disabled for this institute.');
            }

            if (! in_array($feature, (array) ($config['features'] ?? []), true)) {
                abort(403, 'AI feature is disabled for this institute.');
            }
        }

        return $next($request);
    }
}
