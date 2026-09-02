<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global AI switch (platform level)
    |--------------------------------------------------------------------------
    | When false the whole AI layer is off regardless of per-institute toggles.
    | The runtime value can be overridden by the super-admin through the
    | `ai.enabled` platform setting (App\Support\AiConfig reads settings first).
    */

    'enabled' => (bool) env('AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Active provider
    |--------------------------------------------------------------------------
    */

    'provider' => env('AI_PROVIDER', 'openai'),

    'default_model' => env('AI_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Provider definitions
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'openai' => [
            'api_key' => env('AI_OPENAI_API_KEY', ''),
            'base_url' => env('AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('AI_OPENAI_TIMEOUT', 60),
            'model' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'anthropic' => [
            'api_key' => env('AI_ANTHROPIC_API_KEY', ''),
            'base_url' => env('AI_ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'timeout' => (int) env('AI_ANTHROPIC_TIMEOUT', 60),
            'model' => env('AI_ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
        ],
        'gemini' => [
            'api_key' => env('AI_GEMINI_API_KEY', ''),
            'base_url' => env('AI_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'timeout' => (int) env('AI_GEMINI_TIMEOUT', 60),
            'model' => env('AI_GEMINI_MODEL', 'gemini-1.5-flash'),
        ],
        'groq' => [
            'api_key' => env('AI_GROQ_API_KEY', ''),
            'base_url' => env('AI_GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'timeout' => (int) env('AI_GROQ_TIMEOUT', 60),
            'model' => env('AI_GROQ_MODEL', 'llama-3.1-8b-instant'),
        ],
        'custom' => [
            'api_key' => env('AI_CUSTOM_API_KEY', ''),
            'base_url' => env('AI_CUSTOM_BASE_URL', 'https://api.example.com'),
            'timeout' => (int) env('AI_CUSTOM_TIMEOUT', 60),
            'model' => env('AI_CUSTOM_MODEL', 'custom-model'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation defaults
    |--------------------------------------------------------------------------
    */

    'max_tokens' => (int) env('AI_MAX_TOKENS', 900),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),

    'max_tool_rounds' => (int) env('AI_MAX_TOOL_ROUNDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Global system instructions prepended to every AI request.
    |--------------------------------------------------------------------------
    */

    'global_instructions' => (string) env('AI_GLOBAL_INSTRUCTIONS', ''),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | store_prompts controls whether the raw user prompt is persisted in the
    | ai_logs table. Keep it off unless explicitly enabled by the super-admin.
    |--------------------------------------------------------------------------
    */

    'log' => [
        'store_prompts' => (bool) env('AI_LOG_PROMPTS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI features (modular, independently enabled per institute)
    |--------------------------------------------------------------------------
    */

    'features' => [
        'assistant',
        'analytics',
        'content',
        'reports',
        'automation',
    ],
];
