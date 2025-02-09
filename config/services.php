<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sonarr' => [
        'url' => env('SONARR_URL'),
        'api_key' => env('SONARR_API_KEY'),
    ],
    'radarr' => [
        'url' => env('RADARR_URL'),
        'api_key' => env('RADARR_API_KEY'),
    ],
    'lidarr' => [
        'url' => env('LIDARR_URL'),
        'api_key' => env('LIDARR_API_KEY'),
    ],

    'trakt' => [
        'client_id' => env('TRAKT_CLIENT_ID'),
        'client_secret' => env('TRAKT_CLIENT_SECRET'),
        'redirect' => env('TRAKT_REDIRECT_URI'),
    ],

    'plex' => [
        'url' => env('PLEX_URL'),
        'token' => env('PLEX_TOKEN'),
    ],


    'laravelpassport' => [
        'client_id' => env('LARAVEL_PASSPORT_CLIENT_ID'),
        'client_secret' => env('LARAVEL_PASSPORT_CLIENT_SECRET'),
        'redirect' => env('LARAVEL_PASSPORT_REDIRECT'),
        'host' => env('LARAVEL_PASSPORT_HOST')
    ],
];
