<?php

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
    'timeout' => 25,
    'max_output_tokens' => 2500,
];
