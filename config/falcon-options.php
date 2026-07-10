<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue outgoing mail
    |--------------------------------------------------------------------------
    | When true, FalconCMS mailables (order notifications, magic-login,
    | password-reset, email-verification) are pushed onto the queue instead of
    | being sent inside the web request — so checkout/login stay fast under load.
    | Requires a running queue worker (`php artisan queue:work`).
    |
    | When false (default), mail is sent synchronously (the connection is forced
    | to "sync"), preserving the no-worker behaviour so existing installs never
    | silently stop sending mail. Toggle with FALCON_QUEUE_MAIL in .env.
    */
    'queue_mail' => env('FALCON_QUEUE_MAIL', false),

    'hooks' => [
        'general-settings' => [
            'fields' => []
        ],
    ],
    'pages' => []
];
