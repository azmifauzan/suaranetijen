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

    'carisinyal' => [
        'base_url' => env('CARISINYAL_BASE_URL', 'https://carisinyal.com'),
        'feed_url' => env('CARISINYAL_FEED_URL', 'https://carisinyal.com/feed'),
    ],

    'femaledaily' => [
        'base_url' => env('FEMALEDAILY_BASE_URL', 'https://femaledaily.com'),
        'reviews_base_url' => env('FEMALEDAILY_REVIEWS_BASE_URL', 'https://reviews.femaledaily.com'),
    ],

    'detik' => [
        'base_url' => env('DETIK_BASE_URL', 'https://www.detik.com'),
        'comment_api_url' => env('DETIK_COMMENT_API_URL', 'https://apicomment.detik.com/graphql'),
        'max_comment_pages' => (int) env('DETIK_MAX_COMMENT_PAGES', 3),
        'desks' => [
            'https://oto.detik.com/motor/sitemap_news.xml',
            'https://wolipop.detik.com/fashion/sitemap_news.xml',
            'https://wolipop.detik.com/beauty/sitemap_news.xml',
            'https://hot.detik.com/celebs/sitemap_news.xml',
            'https://sport.detik.com/sepakbola/sitemap_news.xml',
            'https://sport.detik.com/raket/sitemap_news.xml',
            'https://finance.detik.com/berita-ekonomi-bisnis/sitemap_news.xml',
            'https://hot.detik.com/music/sitemap_news.xml',
        ],
    ],

];
