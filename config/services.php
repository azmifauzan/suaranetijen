<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'flaresolverr' => [
        'url' => env('FLARESOLVERR_URL'),
        // Kept below the ingestion jobs' 90s $timeout (see FetchSourceDocumentJob /
        // DiscoverSourceDocumentsJob) so a slow-but-solvable challenge doesn't get killed
        // by the job timeout before FlareSolverr even responds.
        'max_timeout_ms' => env('FLARESOLVERR_MAX_TIMEOUT_MS', 45000),
    ],

    // Fallback defaults used only until an admin saves a row in llm_settings
    // (see LlmClient::resolveSettings()) — every LLM-backed feature shares
    // that one settings row instead of reading env/config directly.
    'llm' => [
        'base_url' => env('LLM_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('LLM_MODEL', 'gpt-4o-mini'),
        'api_key' => env('LLM_API_KEY'),
        'max_tokens' => (int) env('LLM_MAX_TOKENS', 1024),
        'temperature' => (float) env('LLM_TEMPERATURE', 0.2),
        'timeout_seconds' => (int) env('LLM_TIMEOUT_SECONDS', 30),
    ],

];
