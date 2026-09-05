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

    'google_books' => [
        'base_url' => env('GOOGLE_BOOKS_BASE_URL', 'https://www.googleapis.com/books/v1'),
        'key' => env('GOOGLE_BOOKS_API_KEY'),
        'max_results' => (int) env('GOOGLE_BOOKS_MAX_RESULTS', 6),
        'connect_timeout' => (int) env('GOOGLE_BOOKS_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('GOOGLE_BOOKS_TIMEOUT', 8),
    ],

    'anilist' => [
        'base_url' => env('ANILIST_BASE_URL', 'https://graphql.anilist.co'),
        'max_results' => (int) env('ANILIST_MAX_RESULTS', 6),
        'connect_timeout' => (int) env('ANILIST_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('ANILIST_TIMEOUT', 12),
    ],

    'comic_vine' => [
        'base_url' => env('COMIC_VINE_BASE_URL', 'https://comicvine.gamespot.com/api'),
        'key' => env('COMIC_VINE_API_KEY'),
        'user_agent' => env('COMIC_VINE_USER_AGENT', 'LiteratureSocialDiscovery/1.0 academic-project'),
        'max_results' => (int) env('COMIC_VINE_MAX_RESULTS', 6),
        'cache_minutes' => (int) env('COMIC_VINE_CACHE_MINUTES', 30),
        'connect_timeout' => (int) env('COMIC_VINE_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('COMIC_VINE_TIMEOUT', 12),
    ],

];
