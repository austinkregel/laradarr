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
        'timeout' => (int) env('SONARR_TIMEOUT', 15),
        'retry' => [
            'times' => (int) env('SONARR_RETRY_TIMES', 3),
            'sleep_ms' => (int) env('SONARR_RETRY_SLEEP_MS', 250),
        ],
        'rate_limit' => [
            'per_minute' => env('SONARR_RATE_LIMIT_PER_MINUTE') !== null
                ? (int) env('SONARR_RATE_LIMIT_PER_MINUTE')
                : null,
        ],
    ],
    'radarr' => [
        'url' => env('RADARR_URL'),
        'api_key' => env('RADARR_API_KEY'),
        'timeout' => (int) env('RADARR_TIMEOUT', 15),
        'retry' => [
            'times' => (int) env('RADARR_RETRY_TIMES', 3),
            'sleep_ms' => (int) env('RADARR_RETRY_SLEEP_MS', 250),
        ],
        'rate_limit' => [
            'per_minute' => env('RADARR_RATE_LIMIT_PER_MINUTE') !== null
                ? (int) env('RADARR_RATE_LIMIT_PER_MINUTE')
                : null,
        ],
    ],
    'lidarr' => [
        'url' => env('LIDARR_URL'),
        'api_key' => env('LIDARR_API_KEY'),
        'api_prefix' => env('LIDARR_API_PREFIX', '/api/v1'),
        'timeout' => (int) env('LIDARR_TIMEOUT', 15),
        'retry' => [
            'times' => (int) env('LIDARR_RETRY_TIMES', 3),
            'sleep_ms' => (int) env('LIDARR_RETRY_SLEEP_MS', 250),
        ],
        'rate_limit' => [
            'per_minute' => env('LIDARR_RATE_LIMIT_PER_MINUTE') !== null
                ? (int) env('LIDARR_RATE_LIMIT_PER_MINUTE')
                : null,
        ],
    ],

    'trakt' => [
        'base_url' => env('TRAKT_BASE_URL', 'https://api.trakt.tv'),
        'client_id' => env('TRAKT_CLIENT_ID'),
        'client_secret' => env('TRAKT_CLIENT_SECRET'),
        'redirect' => env('TRAKT_REDIRECT_URI'),
        'access_token' => env('TRAKT_ACCESS_TOKEN'),
        'refresh_token' => env('TRAKT_REFRESH_TOKEN'),
        'timeout' => (int) env('TRAKT_TIMEOUT', 15),
        'retry' => [
            'times' => (int) env('TRAKT_RETRY_TIMES', 3),
            'sleep_ms' => (int) env('TRAKT_RETRY_SLEEP_MS', 250),
        ],
        'rate_limit' => [
            'per_minute' => env('TRAKT_RATE_LIMIT_PER_MINUTE') !== null
                ? (int) env('TRAKT_RATE_LIMIT_PER_MINUTE')
                : null,
        ],
    ],

    'plex' => [
        'url' => env('PLEX_URL'),
        'token' => env('PLEX_TOKEN', env('PLEX_API_TOKEN')),
        'timeout' => (int) env('PLEX_TIMEOUT', 15),
        'retry' => [
            'times' => (int) env('PLEX_RETRY_TIMES', 3),
            'sleep_ms' => (int) env('PLEX_RETRY_SLEEP_MS', 250),
        ],
        'rate_limit' => [
            'per_minute' => env('PLEX_RATE_LIMIT_PER_MINUTE') !== null
                ? (int) env('PLEX_RATE_LIMIT_PER_MINUTE')
                : null,
        ],
    ],

    'tmdb' => [
        'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
        // TMDB v3 API key (query-string based). If you use a v4 bearer token, we can adjust later.
        'api_key' => env('TMDB_API_KEY'),
        // TMDB v4 auth token (Authorization: Bearer ...)
        'bearer_token' => env('TMDB_BEARER_TOKEN'),
        'timeout' => (int) env('TMDB_TIMEOUT', 15),
        'retry' => [
            'times' => (int) env('TMDB_RETRY_TIMES', 3),
            'sleep_ms' => (int) env('TMDB_RETRY_SLEEP_MS', 250),
        ],
    ],

    'qbittorrent' => [
        'url' => env('QBITTORRENT_URL', 'http://localhost:8080'),
        'username' => env('QBITTORRENT_USERNAME', 'admin'),
        'password' => env('QBITTORRENT_PASSWORD', 'adminadmin'),
        'timeout' => (int) env('QBITTORRENT_TIMEOUT', 15),
        'retry' => [
            'times' => (int) env('QBITTORRENT_RETRY_TIMES', 3),
            'sleep_ms' => (int) env('QBITTORRENT_RETRY_SLEEP_MS', 250),
        ],
        'rate_limit' => [
            'per_minute' => env('QBITTORRENT_RATE_LIMIT_PER_MINUTE') !== null
                ? (int) env('QBITTORRENT_RATE_LIMIT_PER_MINUTE')
                : null,
        ],
    ],


    'laravelpassport' => [
        'client_id' => env('LARAVEL_PASSPORT_CLIENT_ID'),
        'client_secret' => env('LARAVEL_PASSPORT_CLIENT_SECRET'),
        'redirect' => env('LARAVEL_PASSPORT_REDIRECT'),
        'host' => env('LARAVEL_PASSPORT_HOST')
    ],
];
