<?php

return [

    /*
    | AI provider used for CV analysis and job matching.
    | Supported: "anthropic". Leave empty to disable AI features (endpoints return 503).
    */
    'provider' => env('AI_PROVIDER'),

    // Never commit this value; it is read from the environment only and never returned by the API.
    'api_key' => env('AI_API_KEY'),

    'model' => env('AI_MODEL', 'claude-opus-5-5'),

    // low | medium | high | xhigh | max — trades depth of reasoning against latency and cost.
    'effort' => env('AI_EFFORT', 'medium'),

    // Seconds per request (the SDK retries transient failures up to max_retries times).
    'timeout' => (float) env('AI_TIMEOUT', 120),
    'max_retries' => (int) env('AI_MAX_RETRIES', 2),

    // Let the provider retry on its recommended fallback model when a request is declined by a safety policy.
    'fallbacks' => (bool) env('AI_FALLBACKS', true),

    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 16000),

];
