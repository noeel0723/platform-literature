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

    'hardcover' => [
        'base_url' => env('HARDCOVER_BASE_URL', 'https://api.hardcover.app/v1/graphql'),
        'token' => env('HARDCOVER_API_TOKEN'),
        'user_agent' => env('HARDCOVER_USER_AGENT', 'Literahaven/1.0 academic-project'),
        'max_results' => (int) env('HARDCOVER_MAX_RESULTS', 6),
        'cache_minutes' => (int) env('HARDCOVER_CACHE_MINUTES', 30),
        'connect_timeout' => (int) env('HARDCOVER_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('HARDCOVER_TIMEOUT', 10),
    ],

    'knowledge_graph' => [
        'base_url' => env('GOOGLE_KNOWLEDGE_GRAPH_BASE_URL', 'https://kgsearch.googleapis.com/v1/entities:search'),
        'key' => env('GOOGLE_KNOWLEDGE_GRAPH_API_KEY') ?: env('GOOGLE_BOOKS_API_KEY'),
        'language' => env('GOOGLE_KNOWLEDGE_GRAPH_LANGUAGE', 'en'),
        'candidate_limit' => (int) env('GOOGLE_KNOWLEDGE_GRAPH_CANDIDATE_LIMIT', 5),
        'sync_item_limit' => (int) env('GOOGLE_KNOWLEDGE_GRAPH_SYNC_ITEM_LIMIT', 4),
        'cache_days' => (int) env('GOOGLE_KNOWLEDGE_GRAPH_CACHE_DAYS', 30),
        'connect_timeout' => (int) env('GOOGLE_KNOWLEDGE_GRAPH_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('GOOGLE_KNOWLEDGE_GRAPH_TIMEOUT', 8),
    ],

    'work_metadata' => [
        'wikidata_url' => env('WIKIDATA_API_URL', 'https://www.wikidata.org/w/api.php'),
        'wikidata_sparql_url' => env('WIKIDATA_SPARQL_URL', 'https://query.wikidata.org/sparql'),
        'content_language' => env('WORK_METADATA_CONTENT_LANGUAGE', 'en'),
        'wikipedia_summary_url' => env(
            'WIKIPEDIA_SUMMARY_URL',
            'https://{language}.wikipedia.org/api/rest_v1/page/summary/{title}',
        ),
        'user_agent' => env('WORK_METADATA_USER_AGENT', 'Literahaven/1.0 academic-project'),
        'cache_days' => (int) env('WORK_METADATA_CACHE_DAYS', 30),
        'connect_timeout' => (int) env('WORK_METADATA_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('WORK_METADATA_TIMEOUT', 8),
    ],

    'anilist' => [
        'base_url' => env('ANILIST_BASE_URL', 'https://graphql.anilist.co'),
        'max_results' => (int) env('ANILIST_MAX_RESULTS', 6),
        'author_cache_days' => (int) env('ANILIST_AUTHOR_CACHE_DAYS', 30),
        'connect_timeout' => (int) env('ANILIST_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('ANILIST_TIMEOUT', 12),
    ],

    'kitsu' => [
        'base_url' => env('KITSU_BASE_URL', 'https://kitsu.io/api/edge'),
        'user_agent' => env('KITSU_USER_AGENT', 'Literahaven/1.0 academic-project'),
        'max_results' => (int) env('KITSU_MAX_RESULTS', 6),
        'cache_minutes' => (int) env('KITSU_CACHE_MINUTES', 30),
        'connect_timeout' => (int) env('KITSU_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('KITSU_TIMEOUT', 12),
    ],

    'mangaupdates' => [
        'base_url' => env('MANGAUPDATES_BASE_URL', 'https://api.mangaupdates.com/v1'),
        'user_agent' => env('MANGAUPDATES_USER_AGENT', 'Literahaven/1.0 academic-project'),
        'cache_days' => (int) env('MANGAUPDATES_CACHE_DAYS', 30),
        'connect_timeout' => (int) env('MANGAUPDATES_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('MANGAUPDATES_TIMEOUT', 12),
    ],

    'comic_vine' => [
        'base_url' => env('COMIC_VINE_BASE_URL', 'https://comicvine.gamespot.com/api'),
        'key' => env('COMIC_VINE_API_KEY'),
        'user_agent' => env('COMIC_VINE_USER_AGENT', 'Literahaven/1.0 academic-project'),
        'max_results' => (int) env('COMIC_VINE_MAX_RESULTS', 6),
        'creator_enrichment_limit' => (int) env('COMIC_VINE_CREATOR_ENRICHMENT_LIMIT', 4),
        'cache_minutes' => (int) env('COMIC_VINE_CACHE_MINUTES', 30),
        'connect_timeout' => (int) env('COMIC_VINE_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('COMIC_VINE_TIMEOUT', 12),
    ],

];
