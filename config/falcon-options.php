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

    /*
    |--------------------------------------------------------------------------
    | Freemium grace cutoff
    |--------------------------------------------------------------------------
    | A single global date until which every Pro feature stays free (the launch
    | grace window). On/after this date Pro features lock unless licensed. It is
    | a fixed calendar date for all sites — not a rolling per-install window —
    | so the freemium starts everywhere at once. Override with FALCON_GRACE_UNTIL.
    */
    'freemium_grace_until' => env('FALCON_GRACE_UNTIL', '2026-08-01'),

    /*
    |--------------------------------------------------------------------------
    | Upgrade / pricing URL
    |--------------------------------------------------------------------------
    | Where every "Upgrade to Pro" call-to-action points (Pro-required page,
    | freemium banners, analytics/library/layout upgrade buttons, etc.).
    | Override with FALCON_UPGRADE_URL.
    */
    'upgrade_url' => env('FALCON_UPGRADE_URL', 'https://falconcms.com/#pricing'),

    /*
    |--------------------------------------------------------------------------
    | Plugins forced off
    |--------------------------------------------------------------------------
    | Slugs listed here never load, whatever the Plugins screen says — an emergency
    | switch for a plugin that breaks a site before its admin can be reached (e.g.
    | FALCON_DISABLED_PLUGINS=falcon-shop). Comma-separated in the env.
    */
    'disabled_plugins' => array_filter(array_map('trim', explode(',', (string) env('FALCON_DISABLED_PLUGINS', '')))),

    'hooks' => [
        'general-settings' => [
            'fields' => [],
        ],
    ],
    'pages' => [],
];
