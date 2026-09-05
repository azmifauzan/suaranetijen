<?php

return [

    'youtube' => [
        'api_url' => env('YOUTUBE_API_URL', 'https://www.googleapis.com/youtube/v3'),
        'api_key' => env('YOUTUBE_API_KEY'),
        'max_results' => (int) env('YOUTUBE_MAX_RESULTS', 50),
        'max_comment_pages' => (int) env('YOUTUBE_MAX_COMMENT_PAGES', 3),
    ],

    'kaskus' => [
        'base_url' => env('KASKUS_BASE_URL', 'https://www.kaskus.co.id'),
        'listing_url' => env('KASKUS_LISTING_URL'),
    ],

    'lowendtalk' => [
        'base_url' => env('LOWENDTALK_BASE_URL', 'https://lowendtalk.com'),
        'category_urls' => [
            'https://lowendtalk.com/categories/reviews',
            'https://lowendtalk.com/categories/providers',
            'https://lowendtalk.com/categories/outages',
        ],
    ],

    'mediakonsumen' => [
        'base_url' => env('MEDIAKONSUMEN_BASE_URL', 'https://mediakonsumen.com'),
        'feed_url' => env('MEDIAKONSUMEN_FEED_URL', 'https://mediakonsumen.com/feed'),
    ],

    'mojok' => [
        'base_url' => env('MOJOK_BASE_URL', 'https://mojok.co'),
        'feed_url' => env('MOJOK_FEED_URL', 'https://mojok.co/esai/feed'),
    ],

];
