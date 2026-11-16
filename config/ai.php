<?php

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
    'gemini_api_key' => env('GEMINI_API_KEY'),
    'gemini_model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    'ca_bundle' => env('AI_CA_BUNDLE'),
    'timeout' => 25,
    'gemini_timeout' => 25,
    'max_output_tokens' => 2500,
];
