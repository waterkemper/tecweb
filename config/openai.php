<?php

declare(strict_types=1);

return [
    'api_key' => env('OPENAI_API_KEY'),
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    | Set to false only for local dev when PHP has no CA bundle (Windows).
    | Never disable in production.
    */

    'ssl_verify' => filter_var(env('OPENAI_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN),
];
