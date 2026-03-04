<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Zendesk API Configuration
    |--------------------------------------------------------------------------
    */

    'subdomain' => env('ZENDESK_SUBDOMAIN'),
    'email' => env('ZENDESK_EMAIL'),
    'api_token' => env('ZENDESK_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Requests per minute. Zendesk plans: Team=200, Growth=400, Professional=400,
    | Enterprise=700, Enterprise Plus=2500
    */

    'rate_limit_per_minute' => (int) env('ZENDESK_RATE_LIMIT_PER_MINUTE', 400),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    | Set to false only for local dev when PHP has no CA bundle (Windows).
    | Never disable in production.
    */

    'ssl_verify' => filter_var(env('ZENDESK_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */

    'initial_backfill_years' => (int) env('ZENDESK_INITIAL_BACKFILL_YEARS', 2),
    'sync_interval_minutes' => (int) env('ZENDESK_SYNC_INTERVAL_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Sync Filters (optional)
    |--------------------------------------------------------------------------
    | Only sync tickets matching these filters. Leave empty to sync all.
    | For requester filter: run "zendesk:sync --users-only" first to load users.
    */

    'filter_requester_emails' => array_filter(array_map('trim', explode(',', env('ZENDESK_FILTER_REQUESTER_EMAILS', '')))),
    'filter_requester_zd_ids' => array_filter(array_map('intval', explode(',', env('ZENDESK_FILTER_REQUESTER_ZD_IDS', '')))),
    'exclude_statuses' => array_filter(array_map('trim', explode(',', env('ZENDESK_EXCLUDE_STATUSES', 'closed')))),

    /*
    |--------------------------------------------------------------------------
    | Ticket Age Highlighting (for open/pending tickets)
    |--------------------------------------------------------------------------
    | Days since last Zendesk update to flag as "old" or "too old".
    */

    'ticket_age_old_days' => (int) env('ZENDESK_TICKET_AGE_OLD_DAYS', 3),
    'ticket_age_too_old_days' => (int) env('ZENDESK_TICKET_AGE_TOO_OLD_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Priority Order (for sorting)
    |--------------------------------------------------------------------------
    | Order of priorities from highest to lowest. First = most urgent.
    | Null/empty priority is treated as lowest.
    | Customize via ZENDESK_PRIORITY_ORDER=urgent,high,normal,low in .env
    */

    'priority_order' => array_filter(array_map('trim', explode(',', env('ZENDESK_PRIORITY_ORDER', 'urgent,high,normal,low')))),

];
